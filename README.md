## Pushwoosh extension for Magento2
<!--Name of the project -->

Compatible with Community Edition, Commerce and Cloud.  
Pushwoosh extension gives you cross channel marketing automation.

### Features:
* Customer sync
* Orders sync
* Abandoned cart sync

### Web notifications token
Since 1.0.3 the server **API Key** is no longer printed in storefront pages. The storefront Web SDK takes an
optional **Device** API token instead: Stores → Configuration → Pushwoosh → Web Notifications → **Web SDK Token**
(create it in Pushwoosh Control Panel → Settings → API Access). Web push works without it. The token is required
only when the application checks Device API tokens (the setting Pushwoosh support switches on); then the
storefront stops registering subscribers until the token is set.

### On-site recommendations
Stores → Configuration → Pushwoosh → Web Notifications → **On-site Recommendations** = Yes:
- opens the Pushwoosh onsite recommendations feed for the application (a call with the API Key, limited to the
  origins of this Magento's storefronts) and shows a "You viewed / Popular right now" block on the 404 page;
- needs the products in Pushwoosh Product Catalog (a feed URL, e.g. a Google Merchant or YML export).

More blocks: Content → Widgets → **Pushwoosh Recommendations**: strategy (viewed / bestsellers / bought together),
fallback, titles, number of products, block ID for analytics. "Bought together" anchors on the product shown on a
product page, and on the first cart item on the cart page; the block never offers what is already in the cart there
or the product on screen. With Web Notifications on, every product page sends `PW_ProductView` (the input of "viewed" and of
the product pairs). The blocks are rendered by the Web SDK, version 3.79 or newer.

### Upgrading from the Omotech build
Some shops run an early build of this extension under the `Omotech_*` module names (`app/code/Omotech`). Settings
are stored under the same `pushwoosh/*` paths, so they survive the switch. Never keep both builds enabled: every
event would be sent twice and the storefront would load the Web SDK twice.
1. Back up the database, then `bin/magento maintenance:enable`.
2. `bin/magento module:disable Omotech_Core Omotech_AbandonedCart Omotech_Customer Omotech_Order Omotech_Web`
   and remove `app/code/Omotech` (or `composer remove` the package it came from).
3. Install this extension (below), run `bin/magento setup:upgrade`, `setup:di:compile`,
   `setup:static-content:deploy` (production mode) and `cache:flush`, then `maintenance:disable`.
4. Check Stores → Configuration → Pushwoosh: the API Key and Application Code are kept. Set the
   **Web SDK Token** (new in 1.0.3) if the application checks Device API tokens.

The customer attributes `pw_customer_id` and `pw_sync_status` and the `quote.pw_sync_status`/`pw_synced_date`
columns are reused as they are. The data patches run again under the new names and only update the existing
attributes. Old rows in `setup_module` and `patch_list` named `Omotech*` are harmless; delete them if you want a
clean table.

### Composer install the package
```
composer require pushwoosh/magento2-integration
```
Run Magento setup to install the module
```
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

<!-- Write description here -->

## System Requirements
* PHP 8+
* Magento 2.3/2.4
* Elastic Search 7
* RabbitMQ
<!-- mention all the system requirements -->
