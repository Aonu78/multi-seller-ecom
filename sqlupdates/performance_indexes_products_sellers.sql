-- Performance indexes for large product/seller catalogs.
-- Run on the production database after taking a backup.

DELIMITER //

DROP PROCEDURE IF EXISTS add_index_if_not_exists//
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_ddl TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = p_table_name
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table_name
          AND index_name = p_index_name
    ) THEN
        SET @index_ddl = p_ddl;
        PREPARE stmt FROM @index_ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

CALL add_index_if_not_exists('products', 'idx_products_admin_listing',
    'ALTER TABLE `products` ADD INDEX `idx_products_admin_listing` (`added_by`, `auction_product`, `wholesale_product`, `digital`, `created_at`)');

CALL add_index_if_not_exists('products', 'idx_products_all_listing',
    'ALTER TABLE `products` ADD INDEX `idx_products_all_listing` (`auction_product`, `wholesale_product`, `parent_id`, `created_at`)');

CALL add_index_if_not_exists('products', 'idx_products_user_listing',
    'ALTER TABLE `products` ADD INDEX `idx_products_user_listing` (`user_id`, `auction_product`, `wholesale_product`, `digital`, `created_at`)');

CALL add_index_if_not_exists('products', 'idx_products_user_parent',
    'ALTER TABLE `products` ADD INDEX `idx_products_user_parent` (`user_id`, `parent_id`)');

CALL add_index_if_not_exists('products', 'idx_products_published_approved',
    'ALTER TABLE `products` ADD INDEX `idx_products_published_approved` (`approved`, `published`, `added_by`)');

CALL add_index_if_not_exists('products', 'idx_products_category',
    'ALTER TABLE `products` ADD INDEX `idx_products_category` (`category_id`)');

CALL add_index_if_not_exists('products', 'idx_products_brand',
    'ALTER TABLE `products` ADD INDEX `idx_products_brand` (`brand_id`)');

CALL add_index_if_not_exists('product_stocks', 'idx_product_stocks_product',
    'ALTER TABLE `product_stocks` ADD INDEX `idx_product_stocks_product` (`product_id`)');

CALL add_index_if_not_exists('product_stocks', 'idx_product_stocks_product_sku',
    'ALTER TABLE `product_stocks` ADD INDEX `idx_product_stocks_product_sku` (`product_id`, `sku`)');

CALL add_index_if_not_exists('product_categories', 'idx_product_categories_product',
    'ALTER TABLE `product_categories` ADD INDEX `idx_product_categories_product` (`product_id`)');

CALL add_index_if_not_exists('product_categories', 'idx_product_categories_category',
    'ALTER TABLE `product_categories` ADD INDEX `idx_product_categories_category` (`category_id`)');

CALL add_index_if_not_exists('product_translations', 'idx_product_translations_product_lang',
    'ALTER TABLE `product_translations` ADD INDEX `idx_product_translations_product_lang` (`product_id`, `lang`)');

CALL add_index_if_not_exists('product_taxes', 'idx_product_taxes_product',
    'ALTER TABLE `product_taxes` ADD INDEX `idx_product_taxes_product` (`product_id`)');

CALL add_index_if_not_exists('shops', 'idx_shops_user',
    'ALTER TABLE `shops` ADD INDEX `idx_shops_user` (`user_id`)');

CALL add_index_if_not_exists('shops', 'idx_shops_verification_created',
    'ALTER TABLE `shops` ADD INDEX `idx_shops_verification_created` (`verification_status`, `created_at`)');

CALL add_index_if_not_exists('users', 'idx_users_type_id',
    'ALTER TABLE `users` ADD INDEX `idx_users_type_id` (`user_type`, `id`)');

CALL add_index_if_not_exists('users', 'idx_users_type_name',
    'ALTER TABLE `users` ADD INDEX `idx_users_type_name` (`user_type`, `name`)');

CALL add_index_if_not_exists('users', 'idx_users_type_verified',
    'ALTER TABLE `users` ADD INDEX `idx_users_type_verified` (`user_type`, `email_verified_at`)');

CALL add_index_if_not_exists('orders', 'idx_orders_delivery_status',
    'ALTER TABLE `orders` ADD INDEX `idx_orders_delivery_status` (`delivery_status`)');

CALL add_index_if_not_exists('orders', 'idx_orders_seller_status_created',
    'ALTER TABLE `orders` ADD INDEX `idx_orders_seller_status_created` (`seller_id`, `delivery_status`, `created_at`)');

CALL add_index_if_not_exists('orders', 'idx_orders_user_created',
    'ALTER TABLE `orders` ADD INDEX `idx_orders_user_created` (`user_id`, `created_at`)');

CALL add_index_if_not_exists('order_details', 'idx_order_details_order',
    'ALTER TABLE `order_details` ADD INDEX `idx_order_details_order` (`order_id`)');

CALL add_index_if_not_exists('order_details', 'idx_order_details_product',
    'ALTER TABLE `order_details` ADD INDEX `idx_order_details_product` (`product_id`)');

CALL add_index_if_not_exists('order_details', 'idx_order_details_seller_status_created',
    'ALTER TABLE `order_details` ADD INDEX `idx_order_details_seller_status_created` (`seller_id`, `delivery_status`, `created_at`)');

CALL add_index_if_not_exists('seller_withdraw_requests', 'idx_seller_withdraw_user_status',
    'ALTER TABLE `seller_withdraw_requests` ADD INDEX `idx_seller_withdraw_user_status` (`user_id`, `status`)');

CALL add_index_if_not_exists('wallets', 'idx_wallets_user_approval_offline',
    'ALTER TABLE `wallets` ADD INDEX `idx_wallets_user_approval_offline` (`user_id`, `approval`, `offline_payment`)');

CALL add_index_if_not_exists('store_product', 'idx_store_product_user_product',
    'ALTER TABLE `store_product` ADD INDEX `idx_store_product_user_product` (`user_id`, `product_id`)');

CALL add_index_if_not_exists('store_product', 'idx_store_product_product_active',
    'ALTER TABLE `store_product` ADD INDEX `idx_store_product_product_active` (`product_id`, `is_active`)');

DROP PROCEDURE IF EXISTS add_index_if_not_exists;
