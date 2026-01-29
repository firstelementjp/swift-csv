# WordPress Plugin Template

A modern WordPress plugin development template with pre-configured code quality tools and development environment automation.

## ✨ Features

- ✅ **PHPCS/PHPCBF** — WordPress coding standards compliant (short array syntax supported)
- ✅ **Prettier** — Automatic formatting for JavaScript/JSON
- ✅ **ESLint** — JavaScript linting based on WordPress rules
- ✅ **VSCode Settings** — Ready-to-use configuration files included
- ✅ **direnv Integration** — Project-specific environment variables and alias management
- 🚀 **One-command Setup** — Easy initialization with initialization script

## 🚀 Quick Start

### 1. Create from Template

Click the "Use this template" button on GitHub, or run:

```bash
git clone https://github.com/firstelementjp/__project-template.git my-plugin
cd my-plugin
```

### 2. Initialize Plugin

Run the initialization script.

```bash
./init.sh your-plugin-slug "Your Plugin Name"
```

### 3. Install Dependencies

init.sh executes this automatically. For manual execution:

```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install
```

### 4. direnv Setup

init.sh automatically updates .envrc. To activate direnv:

```bash
# If direnv is not installed
brew install direnv

# Integrate direnv with your shell
echo 'eval "$(direnv hook zsh)"' >> ~/.zshrc # for zsh
# or
echo 'eval "$(direnv hook bash)"' >> ~/.bashrc # for bash

# Reload your shell
exec $SHELL

# Activate .envrc
direnv allow
```

## 🛠 Development Environment Setup

### Requirements

- PHP 7.4+
- Node.js 16+
- Composer
- direnv (for automatic development environment setup)
- VSCode-compatible editor (recommended)

### Recommended VSCode Extensions

- PHP Sniffer & Beautifier
- ESLint
- Prettier

## 🔄 Development Workflow

### Convenient Aliases

The following aliases are available in `.envrc`:

```bash
cdcore   # Move to includes/core
cdi18n   # Move to includes/i18n
cdadmin  # Move to includes/admin
cdassets # Move to assets
```

### Available Scripts

#### PHP

```bash
# PHP syntax check
composer phpcs

# Auto fix
composer phpcbf
```

#### JavaScript

```bash
# Lint check
npm run lint:js

# Auto fix
npm run lint:js:fix
```

## 🏗 Project Structure

```
.
├── .vscode/          # VSCode settings
├── assets/           # JavaScript/CSS/image files
├── includes/         # Plugin classes
│   ├── core/         # Core functionality
│   ├── admin/        # Admin functionality
│   └── i18n/         # Internationalization
├── languages/        # Translation files
├── vendor/           # Composer dependencies
├── .envrc            # direnv settings (added to .gitignore)
├── .envrc.example    # Environment settings template
├── .eslintrc.json    # ESLint settings
├── .prettierrc       # Prettier settings
├── phpcs.xml.dist    # PHP_CodeSniffer settings
├── composer.json     # PHP dependency management
├── package.json      # JavaScript dependency management
├── init.sh           # Initialization script
├── plugin.php        # Main plugin file
└── README.md         # This file
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
    ```bash
    git checkout -b feature/AmazingFeature
    ```
3. Commit your changes
    ```bash
    git commit -m 'Add some AmazingFeature'
    ```
4. Push to the branch
    ```bash
    git push origin feature/AmazingFeature
    ```
5. Create a Pull Request

## 📄 License

This project is licensed under the GPLv2+ License. See the `LICENSE` file for details.

## ❤️ Author

Made with ❤️ by Daijiro Miyazawa
