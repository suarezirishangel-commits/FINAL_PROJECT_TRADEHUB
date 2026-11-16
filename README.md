# TradeHub — Startup-Oriented Web Application with CMS and System Integration

## Project Title
TradeHub: Startup-Oriented Web Application Development with CMS and System Integration

## Project Description
TradeHub is a responsive web-based business application designed to address a real-world local commerce problem by connecting buyers, sellers, and delivery services in one digital platform. The system supports local product discovery, online ordering, digital transactions, and content management through a CMS-based admin interface.

The project is designed as a functional startup solution that can be deployed for real business operations. It combines a customer-facing storefront, administrative management, database integration, CMS capabilities, and system integrations such as email notifications, rider GPS tracking, and WordPress-based content management.

## Project Objectives
At the end of this project, the goal is to:
- Develop a complete web application that solves a practical business need.
- Apply system integration concepts in a working solution.
- Use a CMS platform for content and system management.
- Implement secure user authentication and authorization.
- Design a revenue-generating digital business model.
- Present a valid startup concept with working business features.

## Business Proposal

### Problem Statement
Local sellers and micro-entrepreneurs often struggle to reach nearby customers online without a dedicated platform for product discovery, ordering, and delivery coordination. Customers, meanwhile, need a fast, reliable, and transparent way to browse local goods, place orders, and track deliveries.

### Target Users
- Local customers who want to buy products online
- Local sellers who want to list and manage products
- Delivery riders who handle cash-on-delivery orders
- Administrators who manage products, orders, users, and reports

### Value Proposition
TradeHub offers a simple and practical marketplace where users can browse products, place orders, track deliveries, and manage accounts in one unified system. It reduces friction for both buyers and sellers while supporting local business growth.

### Revenue Model
The system includes a commission-based business model where the platform earns a percentage from gross sales. This revenue model supports the idea of a digital startup that generates income through product transactions and platform fees.

## Required Project Components

### A. Web App / User Portal
The user-facing side includes:
- Home page with business overview and highlights
- Product catalog with search, filtering, sorting, and pagination
- Product detail page with live pricing and stock availability
- User registration and login
- User profile management
- Cart and checkout flow
- Order history and order tracking
- Rider tracking page for live delivery status

### B. Major System Modules
The system includes at least three major modules:
- Product Management
- Online Ordering and Checkout
- Order Tracking and Delivery Management
- User and Role Management
- Sales and Report Management

### C. CMS-Based Admin Panel
The system uses WordPress as the CMS/admin platform for management tasks, including:
- Website content management
- Announcements
- Product and category administration
- User data and role management
- Reports and sales monitoring

### D. System Integration Requirement
The project integrates external systems and digital services, including:
- Gmail SMTP for email notifications
- WordPress headless CMS integration
- Delivery rider GPS tracking with map display
- Order tracking and status updates

### E. Database Requirements
The project uses a relational MySQL database with more than five tables. It follows a structured design and supports core business functions such as users, products, categories, transactions, reports, announcements, and settings.

Core database tables include:
- roles
- users
- categories
- products
- transactions
- order_delivery_tracking
- announcements
- settings

### F. Security Requirements
The system implements:
- Authentication
- Authorization by role
- Password encryption using bcrypt
- Input validation
- Session management
- CSRF protection
- Secure database access using prepared statements

### G. User Roles
The platform supports multiple roles, including:
- Administrator
  - Manage content
  - Manage users
  - View reports
  - Manage orders and products
- Customer
  - Browse products
  - Place orders
  - Manage profile
  - Track order status
- Delivery Rider
  - Receive assigned deliveries
  - Update delivery status and GPS location

## Functional Features

### Storefront Features
- Product browsing and filtering
- Search for products by keyword
- Product detail page
- Add to cart and quantity validation
- Checkout with delivery details
- Order history and tracking
- Profile update and password change

### Admin Features
- Dashboard with sales overview
- Manage products and categories
- Manage orders and delivery assignments
- Manage users and roles
- View commission and sales reports
- Publish announcements and manage site content

### Delivery Rider Features
- Rider login
- Assigned order view
- Delivery status updates
- GPS location sharing
- Route tracking and order progress updates

## CMS and WordPress Integration
The project uses WordPress as the admin and CMS layer for the digital business system. It supports headless content delivery to the storefront and provides a plugin-based admin interface for managing products, orders, users, content, and reports.

This architecture allows the project to demonstrate real CMS integration and system architecture beyond a single PHP app.

## Database Design
The database is implemented as a relational structure using MySQL. It stores business records for products, users, roles, transactions, announcements, and settings. This satisfies the requirement for a minimum of five database tables and relational database design.

## System Requirements and Technology Stack
- PHP 8.x
- MySQL / MariaDB
- WordPress CMS integration
- HTML, CSS, JavaScript
- PDO prepared statements
- PHPMailer for email sending
- Leaflet/OpenStreetMap for rider mapping

## Setup and Local Development
1. Place the project inside the WAMP server directory, typically:
   `C:\wamp64\www\FINAL_PROJECT_TRADEHUB`
2. Start WAMP (Apache + MySQL).
3. Import the database using the SQL files in `sql/`.
4. Update database credentials if needed in `config/config.php`.
5. Open the application in the browser using:
   `http://localhost/FINAL_PROJECT_TRADEHUB/`
6. If WordPress CMS is being used, install WordPress locally and configure the TradeHub plugin.

## Demo Accounts
- Administrator
  - Email: admin@tradehub.test
  - Password: Admin@123
- Customer
  - Email: customer@tradehub.test
  - Password: Customer@123

## Security Notes
- Passwords are stored as hashed values using bcrypt.
- Session management and CSRF protection are implemented.
- Input values are validated before processing.
- Sensitive business functions are restricted by user role.
- Delivery tracking is limited to assigned and authorized users only.

## Summary
TradeHub satisfies the required system components for a startup-oriented web application with CMS integration. It includes a public storefront, admin management, user authentication, database integration, revenue model, security controls, and system integration features needed for a business-focused project.
