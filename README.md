# CampusCycle
## **About The Project**

CampusCycle is a peer-to-peer dynamic web marketplace designed specifically for university students. It solves the problem of expensive shipping and campus waste by connecting students who want to sell their old gear with students looking to buy used items locally.



Built exclusively for the "Old is Gold" hackathon theme, this Type 2 Dynamic Web Server runs entirely on legacy hardware, utilizing a highly optimized, lightweight technology stack to deliver real-time performance without relying on heavy frontend frameworks or cloud databases.



## **Key Features**

* Role-Based Access: Secure user authentication with *bcrypt* password hashing.
* Dynamic Feed: Real-time marketplace homepage generated from database queries.
* In-Built Chat: Long-polling messaging system for private student-to-student communication.
* Offer Management: Create, edit, and delete offers with integrated image uploading.
* Admin Dashboard: Moderation tools for maintaining community guidelines.



## **Technology Stack**

* Frontend: HTML5, CSS3, Vanilla JavaScript
* Backend: PHP 7.4+
* Web Server: Apache2
* Database: MySQL
* Environment: Ubuntu Server Linux



## **Server Setup \& Deployment Guide (Ubuntu)**

Follow these steps in order every time you want to run CampusCycle on the legacy Ubuntu server. All commands are written in *bash.*



### **Starting the Server**

**Step 1** – Start the MySQL database

A MySQL database must be running before the site can load, as all the user's information, offers, and messages are saved in the database.



|*sudo systemctl start mysql*|
|-|



**Step 2** – Start Apache

Start the Apache web server to handle HTTP requests and process the PHP files.



|*sudo systemctl start apache2*|
|-|



Step 3 – Fix File Permissions

Run these commands to make sure the web server has the permission to read all the files and write to the uploads folder to store user images.



|*sudo chown -R www-data:www-data /var/www/html/uploads<br />sudo chmod -R 775 /var/www/html*|
|-|



**Step 4** – Start ngrok

Ngrok creates a secure public HTTPS tunnel so the site can be accessed from any device on the local network (like the judges' phones or laptops).



|*ngrok http 80*|
|-|



After starting ngrok, you will be provided with a link to give to others to grant them access to the website. The terminal output should look like this:



|Forwarding https://maliyah-macronucleate-emmy.ngrok-free.dev -> http://localhost:80|
|-|



**Step 5** – Accessing CampusCycle

To access CampusCycle directly from the host Ubuntu machine, put this into your browser:



|http://localhost/index.php|
|-|



To access CampusCycle from any other device on the network, use your unique ngrok URL:



|https://\[your-ngrok-url]/index.php|
|-|



To access the admin panel with an Administrator account:



|https://\[your-ngrok-url]/admin.php|
|-|





### **Stopping the Server**



When you are finished testing or concluding the demo, stop all the services with these commands to safely shut down the environment.



**Stop ngrok**



|Press Ctrl + C in the terminal window where ngrok is actively running to close the tunnel.|
|-|



**Stop Apache**



|*sudo systemctl stop apache2*|
|-|



**Stop MySQL**



|*sudo systemctl stop mysql*|
|-|





