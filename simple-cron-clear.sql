-- wp_optionsテーブルからcronを削除
DELETE FROM wp_options WHERE option_name = 'cron';
