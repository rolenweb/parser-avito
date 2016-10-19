CREATE TABLE `post` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `saler_name` varchar(255) DEFAULT NULL,
  `saler_phone` text DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `metro` text DEFAULT NULL,
  `lat` varchar(255) DEFAULT NULL,
  `lon` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` ENUM(  'parsed',  'posted' ) NULL DEFAULT 'parsed',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,

  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8; 