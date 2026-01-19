# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial project structure
- Core interfaces: `SessionInterface`, `SessionFactoryInterface`, `ExecutorInterface`
- Configuration model: `Configuration`, `Environment`, `MappedStatement`
- Type handlers: Integer, String, Float, Boolean, DateTime, Enum, JSON
- XML mapper parsing
- Dynamic SQL support: `<if>`, `<choose>`, `<foreach>`, `<where>`, `<set>`
- Result mapping with associations and collections
- First-level cache (session scope)
- CLI tool with `compile`, `validate`, `generate` commands

## [0.1.0] - TBD

### Added
- First public release
