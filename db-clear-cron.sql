-- Clear all cron jobs
DELETE FROM wp_options WHERE option_name = 'cron';

-- Reset basic cron
INSERT INTO wp_options (option_name, option_value, autoload) VALUES 
('cron', 'a:3:{i:1754476400;a:1:{s:17:\"wp_version_check\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}s:7:\"version\";i:2;s:8:\"scheduled\";b:0;}', 'yes');
