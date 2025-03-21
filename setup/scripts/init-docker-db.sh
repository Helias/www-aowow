# download and import TDB
wget https://github.com/TrinityCore/TrinityCore/releases/download/TDB335.21101/TDB_full_world_335.21101_2021_10_15.7z
7z x TDB_full_world_335.21101_2021_10_15.7z
rm TDB_full_world_335.21101_2021_10_15.7z

echo "creating trinitycore_world db"
mysql -u root -ppassword -h aowow-database -e "CREATE DATABASE trinitycore_world;"
mysql -u root -ppassword -h aowow-database -e "CREATE DATABASE trinitycore_auth;"
mysql -u root -ppassword -h aowow-database -e "CREATE DATABASE trinitycore_characters;"

wget https://raw.githubusercontent.com/TrinityCore/TrinityCore/refs/heads/3.3.5/sql/base/auth_database.sql
wget https://raw.githubusercontent.com/TrinityCore/TrinityCore/refs/heads/3.3.5/sql/base/characters_database.sql
mysql -u root -ppassword -h aowow-database trinitycore_auth < "auth_database.sql"
mysql -u root -ppassword -h aowow-database trinitycore_characters < "characters_database.sql"
rm auth_database.sql
rm characters_database.sql

echo "importing TDB (it will take time)"
mysql -u root -ppassword -h aowow-database trinitycore_world < "TDB_full_world_335.21101_2021_10_15.sql"
echo "TDB imported"
rm TDB_full_world_335.21101_2021_10_15.sql

mysql -u root -ppassword -h aowow-database aowow < setup/db_structure.sql

mysql -u root -ppassword -h aowow-database aowow -e "SET GLOBAL range_optimizer_max_mem_size=0;"
mysql -u root -ppassword -h aowow-database aowow -e "UPDATE aowow_config SET value='127.0.0.1:80' WHERE \`key\`='site_host';"
mysql -u root -ppassword -h aowow-database aowow -e "UPDATE aowow_config SET value='127.0.0.1:80/static' WHERE \`key\`='static_host';"
mysql -u root -ppassword -h aowow-database aowow -e "UPDATE aowow_config SET value='3' WHERE \`key\`='debug';"
mysql -u root -ppassword -h aowow-database aowow -e "UPDATE aowow_config SET value='1' WHERE \`key\`='locales';" # EN locale

cd /var/www/html/

echo "
<?php

if (!defined('AOWOW_REVISION'))
    die('illegal access');


\$AoWoWconf['aowow'] = array (
  'host' => 'aowow-database:3306',
  'user' => 'root',
  'pass' => 'password',
  'db' => 'aowow',
  'prefix' => 'aowow_',
);

\$AoWoWconf['world'] = array (
  'host' => 'aowow-database:3306',
  'user' => 'root',
  'pass' => 'password',
  'db' => 'trinitycore_world',
  'prefix' => '',
);

\$AoWoWconf['auth'] = array (
  'host' => 'aowow-database:3306',
  'user' => 'root',
  'pass' => 'password',
  'db' => 'trinitycore_auth',
  'prefix' => '',
);

\$AoWoWconf['characters']['1'] = array (
  'host' => 'aowow-database:3306',
  'user' => 'root',
  'pass' => 'password',
  'db' => 'trinitycore_characters',
  'prefix' => '',
);

?>
" > config/config.php

mkdir -p setup/mpqdata/enus/DBFilesClient/
wget https://github.com/wowgaming/client-data/releases/download/v16/data.zip
unzip data.zip "dbc/*" -d ./
mv dbc/* "setup/mpqdata/enus/DBFilesClient/"
rm data.zip

mkdir -p setup/mpqdata/interface/framexml/
wget https://raw.githubusercontent.com/wowgaming/3.3.5-interface-files/refs/heads/main/GlobalStrings.lua -O setup/mpqdata/interface/framexml/globalstrings.lua

mkdir -p setup/mpqdata/enUS/interface/framexml/
cp setup/mpqdata/interface/framexml/globalstrings.lua setup/mpqdata/enUS/interface/framexml/globalstrings.lua

php aowow --sql

# apache2-foreground
