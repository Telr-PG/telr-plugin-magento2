# Telr Payments Plugin for Magento 2 #

This plugin enables your Magento powered platform to start accepting payments via Telr.

### Installation ###

* Download package and copy "Telr" directory and past it to your app/code/ directory
* After past the source code, you need to run command given below
      * php bin/magento setup:upgrade
      * php bin/magento setup:static-content:deploy -f
      * php bin/magento indexer:reindex
      * php bin/magento cache:clean
      * php bin/magento cache:flush
* Enable and configure Telr Payment in Magento Admin under Stores/Configuration/Payment Methods/Telr Payment
* Add your 'Store ID' and 'Authentication Key' in the admin panel (Note : If you have not 'Store ID' and 'Authentication Key' please contact with Telr support)

### Requirements ###

* Magento 2.4.0 Stable or higher

## License

This repository is available under the [MIT license](LICENSE).
