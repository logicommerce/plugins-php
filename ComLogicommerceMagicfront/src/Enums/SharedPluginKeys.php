<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * This class holds the keys of `shared.plugins`: one per LogiCommerce plugin type, the constant named as the type in
 * PluginConnectorType (`CAPTCHA` => `captcha`). A key already published is never renamed: widgets read it. A new
 * LogiCommerce type gets its key here (SharedPluginKeysTest fails until it has one).
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class SharedPluginKeys extends Enum {

    public const ADDRESS_VALIDATOR = 'addressValidator';

    public const ASSET = 'asset';

    public const BASKET = 'basket';

    public const CAPTCHA = 'captcha';

    public const CONFIRM_ORDER = 'confirmOrder';

    public const CUSTOM_TAG = 'customTag';

    public const DATA = 'data';

    public const DOCUMENT_PAYMENT_SYSTEM = 'documentPaymentSystem';

    public const DOCUMENT_SHIPMENT = 'documentShipment';

    public const EXPRESS_CHECKOUT = 'expressCheckout';

    public const KIMERA = 'kimera';

    public const MAILING_SYSTEM = 'mailingSystem';

    public const MAPS = 'maps';

    public const MARKETING = 'marketing';

    public const MARKETPLACE = 'marketplace';

    public const MAGIC_FRONT = 'magicFront';

    public const OAUTH = 'oauth';

    public const ORDER = 'order';

    public const ORDER_STATUS = 'orderStatus';

    public const PAYMENT_SYSTEM = 'paymentSystem';

    public const PICKUP_POINT_PROVIDER = 'pickupPointProvider';

    public const RELATED_DEFINITION = 'relatedDefinition';

    public const REWARD_POINTS = 'rewardPoints';

    public const RMA = 'rma';

    public const ROUTE = 'route';

    public const SEARCH_ENGINE = 'searchEngine';

    public const SITE_BUILDER = 'siteBuilder';

    public const SHIPMENT = 'shipment';

    public const SHIPPER = 'shipper';

    public const SHIPPING_TYPE = 'shippingType';

    public const TAXES = 'taxes';

    public const TAX_ID_VALIDATOR = 'taxIdValidator';

    public const TRACKER = 'tracker';
}
