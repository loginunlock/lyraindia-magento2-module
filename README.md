[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Total Downloads][ico-downloads]][link-downloads]

## Lyra India Magento 2 Module

LyraIndia payment gateway for Magento 2

## Install

* Go to Magento 2 root folder

* Enter following command to install module:

```bash
composer require lyraindia/lyraindia-magento2-module
```

* Wait while dependencies are updated.

* Enter following commands to enable module:

```bash
php bin/magento module:enable Lyra_LyraIndia --clear-static-content
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```

* Enable and configure `LyraIndia` in *Magento Admin* under `Stores/Configuration/Payment` Methods

[ico-version]: https://img.shields.io/packagist/v/lyraindia/lyraindia-magento2-module.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/lyraindia/lyraindia-magento2-module.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/lyraindia/lyraindia-magento2-module
[link-downloads]: https://packagist.org/packages/lyraindia/lyraindia-magento2-module


## Documentation

* [Lyra India Documentation](https://docs.lyra.com/in/)
* [Lyra India Helpdesk](https://www.lyra.com/in/contact/)

## Support

For bug reports and feature requests directly related to this plugin, please report [here](https://www.lyra.com/in/contact/). 

For general support or questions about your Lyra account, you can reach out by sending a message from [our website](https://www.lyra.com/in/).

## Contributing to the Magento 2 plugin

If you have a patch or have stumbled upon an issue with the Magento 2 plugin, you can contribute this back to the code. 

