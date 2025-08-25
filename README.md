# Overlabels

**The Ultimate Twitch Overlay Management Platform**

Transform your Twitch streams with beautiful, dynamic overlays that respond to your audience in real-time. Overlabels makes it effortless to create, customize, and deploy professional-grade stream overlays without writing a single line of code.

![Built with Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel)
![Vue.js](https://img.shields.io/badge/Vue.js-3-4FC08D?style=flat-square&logo=vue.js)
![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?style=flat-square&logo=typescript)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-4-38B2AC?style=flat-square&logo=tailwind-css)

---

## Why Choose Overlabels?

### **Launch Ready in Minutes**
- **One-click Twitch OAuth** - Start creating immediately with your existing Twitch account
- **Pre-built template library** - Choose from professionally designed overlay kits
- **Instant preview** - See your changes in real-time before going live

### **Code-first Template Builder**
- **NO Drag-and-drop interface** - Advanced code editor for power users
- **Live data integration** - Your overlays automatically display follower counts, subscriber goals, recent followers, and more
- **Custom CSS & HTML** - Full creative control when you need it
- **Export standalone files** - Take your overlays anywhere

### **Real-Time Everything**
- **EventSub integration** - Instant notifications for follows, subs, cheers, and raids
- **Dynamic content updates** - No manual refreshing needed
- **Smart notifications** - Beautiful, customizable alerts that match your brand

### **Secure & Scalable**
- **Token-based security** - Control who can access your overlays
- **Rate limiting** - Built-in protection against abuse
- **Privacy controls** - Public or private overlay sharing
- **Access logging** - Track how your overlays are being used

---

## Perfect For

- **New Streamers** wanting professional overlays without vendor lock-in
- **Experienced Creators** needing advanced customization and control  
- **Overlay Designers** building templates for multiple streamers
- **Development Teams** integrating overlay systems into larger platforms

---

## 🛠 Quick Start

### Prerequisites
- PHP 8.2+ with Composer
- Node.js 18+ with npm
- SQLite (included) or PostgreSQL

### Get Running in 3 Steps

```bash
# 1. Clone and install dependencies
git clone <your-repo-url> overlabels
cd overlabels
composer install && npm install

# 2. Set up your environment
cp .env.example .env
# Add your Twitch Client ID and Secret to .env

# 3. Launch everything at once
composer run dev
```

That's it! Visit `http://localhost:8000` and log in with Twitch to start building.

---

## Features

### **Visual Template System**
- **Smart tag system** - `[[[follower_count]]]`, `[[[latest_follower]]]`, `[[[subscriber_goal]]]` and 50+ more
- **Live preview** - See exactly how your overlay looks with real data
- **Fork templates** - Start with existing designs and make them your own
- **Kit collections** - Curated overlay packages from the community

### **Developer-Friendly Architecture**
- **Modern tech stack** - Laravel 12, Vue 3, TypeScript, Inertia.js
- **RESTful API** - Integrate with external tools and services
- **Webhook system** - Real-time Twitch event processing
- **Queue system** - Reliable background job processing

### **Production Ready**
- **CDN support** - Fast asset delivery worldwide
- **Monitoring** - Built-in telescope debugging and logging
- **Testing** - Comprehensive test suite with Pest framework
- **Deployment** - Optimized for modern hosting platforms

---

## Documentation

### **For Streamers**
- **Dashboard Overview** - Manage all your overlays from one place
- **Template Builder Guide** - Create stunning overlays visually
- **Twitch Integration** - Connect your channel and manage EventSub
- **Sharing & Access** - Control who sees your overlays

### **For Developers**
- **API Reference** - Complete endpoint documentation
- **Template Tag System** - Build dynamic content with ease
- **Webhook Events** - Handle Twitch events in real-time
- **Custom Services** - Extend functionality with your own integrations

---

## Community & Kits

### **Template Marketplace**
Discover overlay kits created by the community:
- **Starter Packs** - Everything new streamers need
- **Gaming Themes** - Genre-specific overlay collections  
- **Seasonal Collections** - Holiday and event-themed overlays
- **Professional Designs** - Corporate and business-ready layouts

### **Share & Collaborate**
- **Public galleries** - Showcase your best overlay designs
- **Fork system** - Build upon others' work (with permission)
- **Attribution tracking** - Credit original creators automatically
- **Community feedback** - Rate and review overlay kits

---

## Deployment

### **Hosting Options**
- **Shared Hosting** - Most providers with PHP 8.2+ support
- **VPS/Cloud** - Full control with services like DigitalOcean, Linode
- **Platform as a Service** - Deploy to Heroku, Vercel, or similar
- **Container Deployment** - Docker support included

### **Production Checklist**
```bash
# Optimize for production
php artisan optimize
php artisan config:cache
php artisan route:cache
npm run build

# Set up queue workers
php artisan queue:work --daemon

# Monitor with Telescope (optional)
php artisan telescope:install
```

---

## Contributing

We love contributions! Whether you're:
- **Reporting bugs** - Help us improve reliability
- **Suggesting features** - Shape the future of Overlabels  
- **Designing templates** - Share your creativity
- **Writing code** - Enhance the platform

Check out our currently very much non-existing [Contributing Guide](CONTRIBUTING.md) to get started.

---

## License & Support

**MIT License** - Use Overlabels however you need, commercially or personally.

- **Community Support** - GitHub Discussions and Issues
- **Business Inquiries** - Contact us for enterprise solutions
- **Updates** - Follow us for the latest features and templates

---

*Built with ❤️ by streamers, for streamers. Make every stream unforgettable with Overlabels.*
