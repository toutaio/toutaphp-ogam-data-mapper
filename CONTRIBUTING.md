# Contributing to Touta Ogam

Thank you for considering contributing to Touta Ogam! This document provides guidelines and information for contributors.

## Code of Conduct

Please be respectful and constructive in all interactions. We aim to foster an inclusive and welcoming community.

## Development Setup

### Requirements

- PHP 8.3+
- Composer
- PDO with SQLite, MySQL, and PostgreSQL drivers

### Installation

```bash
git clone https://github.com/toutaio/toutaphp-ogam.git
cd toutaphp-ogam
composer install
```

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests (requires database services)
composer test:integration

# With coverage report
composer test:coverage
```

### Code Quality

```bash
# Check code style
composer cs:check

# Fix code style
composer cs:fix

# Static analysis
composer analyse

# Run all QA checks
composer qa
```

## Pull Request Process

1. **Fork** the repository and create a feature branch
2. **Write tests** for your changes
3. **Ensure all tests pass** (`composer qa`)
4. **Update documentation** if needed
5. **Submit a pull request** with a clear description

## Commit Convention

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `perf`: Performance improvement
- `refactor`: Code restructuring
- `test`: Test additions or modifications
- `docs`: Documentation changes
- `chore`: Build, CI, or tooling changes

**Examples:**
```
feat(mapper): add support for result maps
fix(type-handler): handle null DateTime correctly
perf(hydration): use constructor injection instead of reflection
docs(readme): add quick start guide
```

## Coding Standards

- Follow PER Coding Style (enforced by PHP CS Fixer)
- Use strict types: `declare(strict_types=1);`
- Write PHPDoc for public APIs
- Prefer `final` classes unless extension is intended
- Use readonly properties/classes where appropriate
- All classes should be in the `Touta\Ogam` namespace

## Architecture Guidelines

### SOLID Principles

- **Single Responsibility**: Each class has one reason to change
- **Open/Closed**: Extend via interfaces, not modification
- **Liskov Substitution**: Implementations are interchangeable
- **Interface Segregation**: Small, focused interfaces
- **Dependency Inversion**: Depend on abstractions

### File Organization

```
src/
├── Attribute/           # PHP attributes for mapping
├── Binding/             # Mapper interface binding
├── Builder/             # Configuration parsing
├── Cache/               # Caching implementations
├── Contract/            # Interfaces
├── DataSource/          # Database connections
├── Exception/           # Custom exceptions
├── Executor/            # SQL execution
├── Mapping/             # Result mapping
├── Scripting/           # Dynamic SQL
├── Session/             # Session management
├── Transaction/         # Transaction handling
└── Type/                # Type handlers
```

## Questions?

Open an issue on GitHub or start a discussion.
