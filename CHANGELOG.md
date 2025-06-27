# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2025-06-27

### Added
- **Modern Output Formats**: Three new command-line flags for enhanced dependency analysis
  - `--ai-prompts`: Adds AI-friendly explanations after each problem for better understanding and "one-shot" problem solving
  - `--v2`: Copy-paste ready format with full file paths and formatted code snippets (automatically enables clean output)
  - `--no-legacy`: Omits traditional output format when used with other modern flags
- **Smart Auto-Enable**: `--v2` automatically enables `--no-legacy` for intuitive clean output
- **Input Validation**: Built-in validation prevents confusing empty output scenarios
- **Comprehensive Test Suite**: 253 unit tests with 541 assertions covering all components
  - Complete coverage for Analysis, Application, Domain, and Exception layers
  - PHPUnit 12 configuration with modern PHP 8 attributes
  - GitHub Actions CI/CD workflow for automated testing
- **Enhanced Documentation**: Updated README with usage examples, output format explanations, and best practices

### Changed
- **Improved User Experience**: Modern flags provide cleaner, more useful output for CI/CD and automation
- **PHP 8 Compatibility**: Updated all test annotations to use PHP 8 attributes instead of legacy `@covers` annotations
- **Dependency Management**: Added `composer.lock` for consistent dependency versions

### Technical Details
- **New Console Methods**: Added mode detection, validation, and output formatting methods
- **Enhanced Result Interface**: Extended dependency result handling for AI-friendly output
- **Backward Compatibility**: All existing functionality preserved - classic output remains default
- **Code Quality**: Comprehensive test coverage ensures reliability and maintainability

### Usage Examples
```bash
# Modern copy-paste ready format (clean by default)
bin/dependencies /path/to/magento2 src/modules --v2

# AI-friendly explanations for instant problem solving
bin/dependencies /path/to/magento2 src/modules --ai-prompts --no-legacy

# Combined modern formats
bin/dependencies /path/to/magento2 src/modules --v2 --ai-prompts
```

This release significantly enhances the developer experience while maintaining full backward compatibility. 