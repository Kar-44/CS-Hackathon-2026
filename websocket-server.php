<?php
// websocket-server.php
require_once 'vendor/autoload.php';
require_once 'Database.php';
require_once 'user-functions.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $users; // Maps connection IDs to user data
    protected $chatSubscriptions; // Maps chat IDs to connection IDs
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
        $this->chatSubscriptions = [];
        echo "WebSocket Server started\n";
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data) {
            echo "Invalid message format\n";
            return;
        }
        
        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
            case 'subscribe':
                $this->handleSubscribe($from, $data);
                break;
            case 'message':
                $this->handleMessage($from, $data);
                break;
            case 'typing':
                $this->handleTyping($from, $data);
                break;
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        // Clean up user data
        if (isset($this->users[$conn->resourceId])) {
            $userId = $this->users[$conn->resourceId]['id'];
            
            // Remove from chat subscriptions
            foreach ($this->chatSubscriptions as $chatId => $connections) {
                if (($key = array_search($conn->resourceId, $connections)) !== false) {
                    unset($this->chatSubscriptions[$chatId][$key]);
                    
                    // Notify others in the chat that user left
                    $this->broadcastToChat($chatId, [
                        'type' => 'user_left',
                        'userId' => $userId
                    ], $conn->resourceId);
                }
            }
            
            unset($this->users[$conn->resourceId]);
        }
        
        $this->clients->detach($conn);
        echo "Connection closed: {$conn->resourceId}\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function handleAuth(ConnectionInterface $conn, $data) {
        $token = $data['token'] ?? '';
        
        // For security, you should validate the session
        session_id($token);
        session_start();
        
        if (isset($_SESSION['user'])) {
            $this->users[$conn->resourceId] = $_SESSION['user'];
            $conn->send(json_encode([
                'type' => 'auth_success',
                'userId' => $_SESSION['user']['id'],
                'username' => $_SESSION['user']['username']
            ]));
            echo "User authenticated: {$_SESSION['user']['username']}\n";
        } else {
            $conn->send(json_encode([
                'type' => 'auth_failed',
                'message' => 'Invalid session'
            ]));
        }
        session_write_close();
    }
    
    private function handleSubscribe(ConnectionInterface $conn, $data) {
        $chatId = $data['chatId'];
        
        if (!isset($this->users[$conn->resourceId])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Not authenticated'
            ]));
            return;
        }
        
        if (!isset($this->chatSubscriptions[$chatId])) {
            $this->chatSubscriptions[$chatId] = [];
        }
        
        // Add to subscription list
        if (!in_array($conn->resourceId, $this->chatSubscriptions[$chatId])) {
            $this->chatSubscriptions[$chatId][] = $conn->resourceId;
        }
        
        echo "User {$this->users[$conn->resourceId]['username']} subscribed to chat {$chatId}\n";
        
        // Confirm subscription
        $conn->send(json_encode([
            'type' => 'subscribed',
            'chatId' => $chatId
        ]));
    }
    
    private function handleMessage(ConnectionInterface $from, $data) {
        $chatId = $data['chatId'];
        $message = $data['message'];
        
        if (!isset($this->users[$from->resourceId])) {
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Not authenticated'
            ]));
            return;
        }
        
        $user = $this->users[$from->resourceId];
        
        // Save to database
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO messages (chat_id, sender_id, message, sent)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$chatId, $user['id'], $message]);
            
            $messageId = $db->lastInsertId();
            
            // Get the message with timestamp
            $msgStmt = $db->prepare("
                SELECT m.*, u.username as sender_username
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.id = ?
            ");
            $msgStmt->execute([$messageId]);
            $savedMsg = $msgStmt->fetch();
            
            // Update chat's last_message_time
            $updateStmt = $db->prepare("
                UPDATE chats SET last_message_time = NOW() WHERE id = ?
            ");
            $updateStmt->execute([$chatId]);
            
            // Broadcast to all subscribers of this chat
            $this->broadcastToChat($chatId, [
                'type' => 'new_message',
                'message' => [
                    'id' => $savedMsg['id'],
                    'sender' => $savedMsg['sender_username'],
                    'text' => $savedMsg['message'],
                    'time' => strtotime($savedMsg['sent'])
                ]
            ], $from->resourceId);
            
        } catch (Exception $e) {
            error_log("Error saving message: " . $e->getMessage());
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Failed to save message'
            ]));
        }
    }
    
    private function handleTyping(ConnectionInterface $from, $data) {
        $chatId = $data['chatId'];
        $isTyping = $data['isTyping'];
        
        if (!isset($this->users[$from->resourceId])) {
            return;
        }
        
        $user = $this->users[$from->resourceId];
        
        // Broadcast typing status to others in the chat
        $this->broadcastToChat($chatId, [
            'type' => 'typing',
            'userId' => $user['id'],
            'username' => $user['username'],
            'isTyping' => $isTyping
        ], $from->resourceId);
    }
    
    private function broadcastToChat($chatId, $data, $excludeConnectionId = null) {
        if (!isset($this->chatSubscriptions[$chatId])) {
            return;
        }
        
        $payload = json_encode($data);
        
        foreach ($this->chatSubscriptions[$chatId] as $connId) {
            if ($excludeConnectionId && $connId == $excludeConnectionId) {
                continue;
            }
            
            // Find the connection object
            foreach ($this->clients as $client) {
                if ($client->resourceId == $connId) {
                    $client->send($payload);
                    break;
                }
            }
        }
    }
}

// Create and run the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8082 // WebSocket port
);

echo "WebSocket server running on port 8082\n";
$server->run();
?>
