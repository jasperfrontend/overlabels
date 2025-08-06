# Overlabels

Welcome to **Overlabels**, a Laravel-based application that enables Twitch streamers to manage overlays, templates, and Twitch EventSub configurations with ease. The app includes features such as advanced template management, Twitch API integrations, event handling, and user authentication powered by Twitch OAuth.

---

## Features

### **Core Functionality**
- **Twitch OAuth Integration:** Secure login using Twitch accounts with scopes to manage user data, subscriptions, followers, and more.
- **Overlay Management:**
  - View public or private overlays.
  - Render overlays programmatically via API.
- **Template Builder:**
  - Create, manage, and fork templates for overlays.
  - Validate and preview templates with sample Twitch data.
  - Export templates as standalone HTML files.
- **Template Tags System:**
  - Generate standardized template tags using live Twitch data.
  - Clear all tags or export for reuse.
  - Preview tags with real-time data.
- **Twitch EventSub API:**
  - Connect/disconnect from Twitch EventSub.
  - View webhook status and manage cleanup processes.

### **Authenticated Features**
- **Dashboard:** Access an overview of your Twitch data and application usage.
- **Twitch Data Management:** Refresh and synchronize various Twitch data points, such as:
  - User profile information
  - Channel followers and subscribers
  - Goals and channel points
- **Access Tokens Management:** Generate and revoke overlay access tokens to manage secured interactions.
- **Customizable Navigation:** Access multiple tools, including the EventSub demo and tags generator directly through the app.

---

## API Overview

### **Public Endpoints**
1. `POST /twitch/webhook`  
   Receives and processes Twitch webhook notifications.

2. **Overlay Rendering:**
  - `POST /overlay/render` - Render overlay templates programmatically.
  - `GET /overlay/{slug}/public` - View public overlays.

### **Authenticated API Endpoints**
- **Twitch Events:**
  - `GET /twitch/events` - List all events.
  - `GET /twitch/events/{id}` - Show details of a specific event.
  - `PUT /twitch/events/{id}/process` - Mark events as processed.
  - `POST /twitch/events/batch-process` - Batch process events.
  - `DELETE /twitch/events/{id}` - Remove specific events.

- **Template Builder API:**
  - `GET /api/template/tags` - Retrieve available template tags.
  - `POST /api/template/validate` - Validate template syntax.
  - `POST /api/template/preview` - Preview templates with sample data.
  - `POST /api/template/export` - Export templates as standalone HTML.
  - `POST /api/template/save` - Save a template to be used in the overlay.
  - `GET /api/template/load/{slug}` - Load an overlay template by slug.

---

## Installation and Setup

### Prerequisites
- [PHP 8.2](https://www.php.net/releases/8.2/en.php)
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) with npm
- A PostgreSQL database

### Steps
1. Clone the repository.
```shell script
git clone <repository-url>
   cd Overlabels
```

2. Install PHP dependencies.
```shell script
composer install
```

3. Install JavaScript dependencies.
```shell script
npm install
```

4. Configure the `.env` file:
  - Connect to your PostgreSQL database.
  - Set up Twitch OAuth credentials (`TWITCH_CLIENT_ID`, `TWITCH_CLIENT_SECRET`).

5. Run database migrations.
```shell script
php artisan migrate
```

6. Start the development server.
```shell script
php artisan serve
```

7. Compile frontend assets.
```shell script
npm run dev
```


---

## Usage

### Authentication
1. Navigate to the root URL.
2. Use the “Log in with Twitch” button to authenticate.

### Dashboard
After logging in, you'll be redirected to the dashboard, which provides access to all the app's tools:
- View and refresh Twitch data.
- Manage templates and overlays.
- Set up EventSub integrations.

### Template Builder
Access the template builder via the **Template Builder** menu to create and modify templates. Use the integrated validation and preview features to ensure templates work seamlessly with your Twitch data.

---

## Navigation
The sidebar provides quick access to the following tools:
- **Dashboard**
- **Your Twitch Data**
- **Tags Generator**
- **Token Generator**
- **Template Builder**
- **EventSub Demo**

---

## Development

### Testing
- Run unit tests:
```shell script
php artisan test
```

- Run frontend tests:
```shell script
npm run test
```


### Code Style
- Use [Prettier](https://prettier.io/) for consistent frontend formatting.
- Use [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) for PHP standards.

---

## License
This project is licensed under the MIT License. See the `LICENSE` file for details.

---

## Contributing
Contributions are always welcome! Fork the repository, create a feature branch, and submit a pull request.

---

Feel free to explore and create custom overlays and templates for Twitch!
