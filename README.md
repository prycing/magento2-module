# Prycing Magento 2 Module

[![Packagist Version](https://img.shields.io/packagist/v/prycing/magento2-module.svg)](https://packagist.org/packages/prycing/magento2-module)
[![Packagist Downloads](https://img.shields.io/packagist/dt/prycing/magento2-module.svg)](https://packagist.org/packages/prycing/magento2-module)
[![GitHub stars](https://img.shields.io/github/stars/Prycing/magento2-module.svg)](https://github.com/Prycing/magento2-module)
[![License](https://img.shields.io/github/license/Prycing/magento2-module.svg)](https://github.com/Prycing/magento2-module)

## Overview

This is the official Magento 2 module for Prycing integration. It enables synchronization of data from the Prycing platform to your Magento 2 store, allowing you to leverage Prycing's services and data within your e-commerce environment.

## Requirements

- Magento 2.x
- PHP compatible with your Magento 2 version
- Composer

## Installation

### Via Composer (recommended)

```bash
composer require prycing/magento2-module
bin/magento module:enable Prycing_Prycing
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Manual Installation

1. Download the latest release from [GitHub](https://github.com/Prycing/magento2-module)
2. Extract the files to `app/code/Prycing/Module`
3. Run the following commands:

```bash
bin/magento module:enable Prycing_Prycing
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

## Configuration

1. Go to Store > Configuration > Prycing > Settings
2. Enter your API credentials
3. Configure the module settings according to your needs
4. Save the configuration

## Features

- Seamless data synchronization between Prycing platform and Magento 2
- Automated import of product data, pricing information, and inventory updates
- Configurable sync intervals and data mapping options
- Real-time or scheduled data transfer options

## Support

For support, please contact:
- [Create an issue on GitHub](https://github.com/Prycing/magento2-module/issues)
- Contact Prycing support directly

## Changelog

### v0.1.4
- Added data synchronization features from Prycing platform
- Improved configuration options for API credentials
- Enhanced documentation

### v0.1.3
- Latest release

### v0.1.2
- Previous release

## License

This module is licensed under the MIT License - see the LICENSE file for details. 