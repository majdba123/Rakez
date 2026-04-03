# 🏢 Rakez ERP - Enterprise Resource Planning System

<p align="center">
  <strong>A comprehensive, enterprise-grade ERP solution for real estate and sales management</strong>
  <br/>
  Built with Laravel 12 | PHP 8.2+ | Real-time Notifications | AI-Powered Features
</p>

<p align="center">
  <a href="#overview">Overview</a> •
  <a href="#key-features">Features</a> •
  <a href="#tech-stack">Tech Stack</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#project-structure">Structure</a> •
  <a href="#modules">Modules</a> •
  <a href="#api-documentation">API</a>
</p>

---

## 📋 Overview

**Rakez ERP** is a comprehensive enterprise resource planning system designed for modern real estate and sales organizations. It provides integrated solutions for project management, sales operations, commission tracking, financial accounting, and team collaboration with advanced features like real-time notifications, AI-powered assistance, and complex commission calculation workflows.

The system supports multi-role organizations with granular permission controls, enabling seamless collaboration across departments including Sales, Marketing, Project Management, HR, Accounting, and more.

### Target Users
- **Sales Teams**: Unit booking, reservation management, commission tracking
- **Project Managers**: Project lifecycle management, contract handling, media management
- **Marketing Teams**: Budget planning, campaign tracking, lead generation
- **Accounting Teams**: Commission distribution, deposit management, financial reporting
- **HR Teams**: Employee management, performance tracking, user administration
- **Executives**: Analytics dashboards, business intelligence, reporting

---

## 🎯 Key Features

### 💰 Commission & Sales Management
- **Automated Commission Calculation**: Intelligent calculation of commissions, VAT, and net amounts
- **Distribution Workflow**: Multi-party commission distribution (Lead Generation, Sales, Closing, Management)
- **Approval Process**: Sales manager review and approval workflow with notifications
- **Refund Management**: Deposit refund processing with complete audit trails
- **Sales Analytics**: Real-time sales metrics, performance dashboards, and trend analysis

### 🎫 Booking & Reservations
- **Waiting List System**: Priority-based queue management with auto-expiry
- **Unit Reservations**: Complex unit booking with multiple status states
- **Contract Management**: Complete contract lifecycle from creation to completion
- **Exclusive Project Requests**: Special project request handling with approval workflows

### 👥 Advanced Roles & Permissions
- **67 Permissions** across 9 predefined roles
- **Admin, Project Management, Sales, Marketing, HR, Accounting, Editing, Developer** roles
- **Dynamic Manager Permissions**: Special permissions for team managers and leaders
- **150+ Protected API Routes**: Fine-grained access control at the endpoint level

### 🔔 Real-Time Notifications
- **Multi-Channel Delivery**: In-app, email, SMS (Twilio integration)
- **Event-Driven Architecture**: Automatic notifications for commission approvals, booking confirmations, task assignments
- **Broadcasting Support**: WebSocket support via Laravel Reverb for real-time updates
- **Notification Templates**: Customizable notification message templates

### 🤖 AI-Powered Features
- **AI Assistant**: OpenAI GPT integration for intelligent assistance
- **Arabic Support**: Full support for Arabic language with AI tools
- **AI Capabilities Matrix**: Fine-grained role-based AI tool access control
- **E2E Testing**: Comprehensive end-to-end tests for AI features

### 📊 Analytics & Reporting
- **Commission Analytics**: Detailed commission tracking and forecasting
- **Sales Dashboards**: Real-time sales metrics and KPI monitoring
- **Financial Reports**: Complete accounting and deposit reports
- **PDF/Excel Export**: Multiple export formats for reports and documents

### 🎬 Media Management
- **Media Upload**: Image and video upload with processing
- **Montage/Editing**: Professional editing capabilities for media assets
- **Digital Asset Management**: Organized media library with tagging and filtering

### 📱 Marketing Management
- **Budget Planning & Tracking**: Marketing budget allocation and monitoring
- **Platform Integration**: Facebook Business SDK and TikTok Marketing API integration
- **Campaign Tracking**: Multi-channel marketing campaign management
- **Performance Analytics**: ROI tracking and campaign performance metrics

### 📞 External Integrations
- **Facebook Business SDK**: Facebook Ads and business tools integration
- **TikTok Marketing API**: TikTok advertising platform integration
- **Twilio SMS**: SMS notifications and communications
- **OpenAI GPT**: AI-powered features and assistance
- **PDF Processing**: PDF generation (DomPDF, mPDF) and parsing

---

## 🛠️ Tech Stack

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: MySQL/PostgreSQL with Eloquent ORM
- **Queue**: Redis/Database queue system
- **Real-Time**: Laravel Reverb (WebSocket broadcasting)
- **Authentication**: Laravel Sanctum (API tokens)

### Authorization
- **Spatie Laravel Permission**: Advanced permission and role management
- **Custom Gates & Policies**: Fine-grained authorization

### Frontend
- **Bundler**: Vite
- **Environment Integration**: Vue/React compatible API layer

### Key Dependencies
```json
{
  "laravel/framework": "^12.0",
  "laravel/reverb": "^1.6",
  "laravel/sanctum": "^4.2",
  "spatie/laravel-permission": "^6.24",
  "maatwebsite/excel": "^3.1.63",
  "openai-php/laravel": "^0.18.0",
  "facebook/php-business-sdk": "22.0",
  "promopult/tiktok-marketing-api": "3.1",
  "twilio/sdk": "^7.0",
  "barryvdh/laravel-dompdf": "*",
  "carlos-meneses/laravel-mpdf": "^2.1"
}
```

### Testing
- **Unit & Feature Tests**: PHPUnit 11.5+
- **Faker**: Data generation
- **Mockery**: Mocking framework
- **E2E Tests**: Comprehensive end-to-end testing

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js 18+
- MySQL 8.0+ or PostgreSQL 13+
- Redis (optional, for queue/caching)

### Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd rakez-erp
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database setup**
```bash
php artisan migrate
php artisan db:seed
```

5. **Build frontend assets**
```bash
npm run build
```

6. **Start development server**
```bash
php artisan serve
```

Access the application at `http://localhost:8000`

### Development Commands

```bash
# Run development environment (concurrent: server, queue, logs, vite)
npm run dev

# Run tests
composer run test

# AI-related tests
composer run test:ai-qa-suite

# Format code
./vendor/bin/pint

# Generate API documentation
php artisan api:generate-docs
```

---

## 📁 Project Structure

```
rakez-erp/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # API & Web Controllers
│   │   ├── Requests/           # Request validation
│   │   └── Middleware/         # Custom middleware
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic services
│   ├── Jobs/                   # Queued jobs
│   ├── Notifications/          # Notification classes
│   ├── Policies/               # Authorization policies
│   ├── Events/                 # Domain events
│   ├── Listeners/              # Event listeners
│   ├── Exceptions/             # Custom exceptions
│   ├── Mail/                   # Mail classes
│   ├── Constants/              # Application constants
│   ├── Enums/                  # PHP enums
│   ├── Helpers/                # Helper functions
│   └── Domain/                 # Domain layer logic
├── routes/
│   ├── api.php                 # API routes (protected)
│   ├── web.php                 # Web routes
│   └── ...
├── database/
│   ├── migrations/             # Database migrations
│   ├── seeders/                # Database seeders
│   └── factories/              # Model factories
├── tests/
│   ├── Feature/                # Feature tests
│   ├── Unit/                   # Unit tests
│   ├── Integration/            # Integration tests
│   └── Support/                # Test helpers
├── config/
│   ├── ai_capabilities.php     # AI features config
│   ├── ai_assistant.php        # AI assistant config
│   └── ...                     # Other configs
├── resources/
│   ├── views/                  # Laravel views
│   └── js/                     # Frontend assets
├── storage/                    # Application storage
├── bootstrap/                  # Bootstrap files
├── public/                     # Public assets
└── vendor/                     # Composer dependencies
```

---

## 📦 Core Modules

### 1. **Commission Module**
- Commission creation from unit sales
- Multi-party distribution (Marketer, Sales, Closer, Manager)
- Management approval workflow
- VAT calculation (15%)
- Complete audit trail
- **Endpoints**: 18 dedicated endpoints

### 2. **Sales Management Module**
- Unit booking and reservations
- Sales analytics and dashboards
- Performance tracking
- Commission tracking per salesperson
- Goal management
- **Endpoints**: Multiple analytics endpoints

### 3. **Deposit Management Module**
- Customer deposits tracking
- Refund processing
- Deposit reconciliation
- Financial reporting
- **Endpoints**: 16 dedicated endpoints

### 4. **Project Management Module**
- Project creation and lifecycle
- Contract management
- Media/attachment handling
- Exclusive project requests
- Team assignment
- **Endpoints**: Project CRUD and workflow endpoints

### 5. **Marketing Module**
- Budget planning and tracking
- Multi-platform campaign management
- Performance analytics
- Task assignment and tracking
- **Endpoints**: Budget, campaign, and analytics endpoints

### 6. **Waiting List Module**
- Priority-based queue system
- Auto-expiry after 30 days
- Conversion to reservations
- Notification integration
- **Endpoints**: 5 dedicated endpoints

### 7. **User & Roles Module**
- User management
- 9 Role types with 67 permissions
- Team management
- User profile management
- **Endpoints**: User management endpoints

### 8. **Notifications Module**
- Multi-channel notifications (In-app, Email, SMS)
- Real-time WebSocket broadcasting
- Event-driven architecture
- Notification templates customization

### 9. **Accounting Module**
- Commission accounting
- Deposit tracking
- Financial reports
- VAT calculations
- Export capabilities (PDF, Excel)

### 10. **AI Assistant Module**
- OpenAI GPT integration
- Arabic language support
- Role-based tool access (67 permissions)
- E2E testing for AI features

---

## 📡 API Documentation

### API Overview
- **Total Routes**: 150+ protected endpoints
- **Authentication**: Laravel Sanctum (Bearer Token)
- **Response Format**: JSON
- **Versioning**: API v1

### Key Endpoint Groups

| Module | Endpoints | Purpose |
|--------|-----------|---------|
| **Commission** | 18 | Commission calculation, distribution, approval |
| **Sales Analytics** | 5 | Sales metrics, dashboards, reports |
| **Deposits** | 16 | Deposit creation, refunds, tracking |
| **Projects** | ~15 | Project CRUD, contract management |
| **Users** | ~10 | User management, profile |
| **Roles & Permissions** | ~8 | Permission/role assignment |
| **Waiting List** | 5 | Queue management, conversions |
| **Marketing** | ~15 | Budget, campaigns, platform integration |
| **Notifications** | ~5 | Message history, preferences |
| **AI Assistant** | ~8 | AI features, query handling |

For complete API documentation, see:
- [API_PERMISSIONS_MAPPING.md](./API_PERMISSIONS_MAPPING.md)
- [POSTMAN_COLLECTION_README.md](./POSTMAN_COLLECTION_README.md)
- [Postman Collection](./Rakez_ERP_Complete_API_Collection.json)

---

## 🔐 Security Features

- **Row-Level Security**: Users see only their authorized data
- **CSRF Protection**: Built-in Laravel CSRF protection
- **Rate Limiting**: API rate limiting per user
- **Sanctum Tokens**: Secure API token-based authentication
- **Audit Trails**: Complete audit logging for sensitive operations
- **Permission Enforcement**: Fine-grained permission checking on all protected routes

---

## 🧪 Testing

The project includes comprehensive test coverage:

```bash
# Run all tests
composer run test

# Run AI feature tests (with real OpenAI calls)
composer run test:e2e-ai

# Run AI quality tests
composer run test:ai-qa-quality

# Run full AI test suite
composer run test:ai-qa-suite

# Run hard proof tests (live validation)
composer run test:ai-hard-proof-live
```

**Test Statistics**:
- 20+ test files
- 100+ test cases
- Unit, feature, and integration tests
- E2E AI feature testing

---

## 📊 Database Schema

### Core Tables
- `users` - User accounts with role and manager flag
- `roles` - Role definitions
- `permissions` - Permission definitions
- `role_has_permissions` - Role-permission associations
- `model_has_roles` - User-role associations
- `model_has_permissions` - Direct user permissions

### Business Domain Tables
- `commissions` - Commission records with calculations
- `commission_distributions` - Multi-party commission allocation
- `deposits` - Customer deposits and refunds
- `contracts` - Sales contracts and agreements
- `contract_units` - Real estate units
- `sales_reservations` - Unit reservations
- `waiting_list_entries` - Queue entries
- `exclusive_project_requests` - Special project requests
- `projects` - Project records
- `teams` - Team organizational structure

---

## 🔄 Key Workflows

### Commission Workflow
1. Unit sold → Commission created
2. Distribution allocation → Multiple roles receive commission shares
3. Sales manager approval → Individual distribution approval
4. Notification sent → Employee notified of approval
5. Payment processing → Commission ready for payout

### Waiting List Workflow
1. Customer enters waiting list → Create entry with expiry
2. Priority management → Queue ordering based on rules
3. Conversion trigger → Convert to reservation when unit available
4. Auto-expiry → Remove from queue after 30 days

### Exclusive Project Workflow
1. Request submission → Employee requests special project
2. Manager approval → Management review and decision
3. Contract completion → Contract finalization
4. Reporting → Completion recorded in system

---

## 🌍 Internationalization

- **Arabic Support**: Full Arabic language support in AI features
- **RTL Support**: Right-to-left text direction support
- **Multi-Language API**: Responses can be localized

---

## 📈 Performance Considerations

- **Database Indexing**: Optimized indexes on frequently queried columns
- **Query Optimization**: Eager loading with Eloquent
- **Caching**: Redis cache for frequently accessed data
- **Queue Processing**: Background jobs for heavy operations
- **Real-Time Events**: WebSocket support via Reverb for live updates

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Standards
- Follow PSR-12 coding standards (enforced with Pint)
- Write tests for new features
- Update documentation with changes
- Ensure all tests pass before submitting PR

---

## 📝 Documentation

Comprehensive documentation is available:

- [System Overview](./SYSTEM_OVERVIEW.md) - Architecture and data flows
- [Commission Implementation](./COMMISSION_SALES_MANAGEMENT_IMPLEMENTATION.md) - Commission system details
- [Roles & Permissions](./ROLES_PERMISSIONS_IMPLEMENTATION.md) - Permission system guide
- [API Permissions Mapping](./API_PERMISSIONS_MAPPING.md) - Complete endpoint permission matrix
- [Quick Start Guide](./QUICK_START_GUIDE.md) - Developer setup
- [Postman Collection](./POSTMAN_COLLECTION_README.md) - API testing with Postman

---

## 🐛 Support & Issues

- Report bugs via GitHub Issues
- Check existing documentation before asking
- Include relevant error messages and logs in bug reports
- Provide reproduction steps for consistent issues

---

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](./LICENSE) file for details.

---

## 👨‍💻 Development Team

Built with modern Laravel best practices and enterprise-grade architecture.

---

## 🚀 Future Roadmap

- [ ] Advanced scheduling and calendar management
- [ ] Integration with additional payment gateways
- [ ] Enhanced mobile app support
- [ ] Advanced business intelligence and BI tools
- [ ] Workflow automation rules engine
- [ ] Multi-tenant support
- [ ] Additional AI capabilities and tools

---

**Last Updated**: April 2026  
**Version**: 1.0.0  
**Status**: Production Ready ✅
