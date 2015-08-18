#!/bin/bash

cd /home/fhulu
server=$1
user=$2
password=$3
db=$4
script="$db.sql"
echo "Generating script $script ..."
mysqldump --compatible=mssql --compact --add-drop-table --skip-extended-insert $db -u$user -p$password >$script
#sed -i "1i if not exists(select * from sysdatabases where name = '$db')\n create database $db\ngo\nuse $db\ngo\n" $script
sed -i -e 's/`//g' $script
sed -i -e 's/((?:var)?char)/n\1/gi' $script
perl -i -pe "s/\" \w*int\(/\" numeric(/g" $script
perl -i -pe 's/ NULL DEFAULT [^,]+(,?)$/ NULL$1/g' $script
perl -i -pe 's/^DROP TABLE IF EXISTS "(\w+)"/IF OBJECT_ID(\047$1\047,\047U\047) IS NOT NULL\nDROP TABLE \047$1\047;\n\n/g' $script
#perl -i -pe 's/^DROP TABLE IF EXISTS "(\w+)"/IF exists (select * from sysobjects where name ="$1" and xtype="U")\n\tDROP TABLE $1/g' $script
perl -i -pe 's/" timestamp/" datetime/g' $script
#perl -i -pe 's/^ +PRIMARY KEY \("(\w+)"\)/  constraint pk_$1 primary key clustered($1)/g' $script
perl -i -pe '/^ +PRIMARY KEY/d' $script
#perl -i -pe 's/^ +KEY "(\w+)" \("(\w+)"\)/  index idx_$1 ($2)/g' $script
sed -i -e '/^  KEY /d' $script
#perl -i -pe 's/insert into "(\w+)"/insert into \047$1\047/ig' $script
#perl -i -pe 's/;$//' $script
sed -i 's/\\"//g' $script
sed -i -e "s/\\\'/''/g" $script
sed -i 's/\/\*/\n--/g' $script
sed -i 's/;$/;\n\n/g' $script
perl -i -pe "s/'0000-00-00 00:00:00'/getdate()/g" $script
perl -i -pe "s/'0000-00-00'/getdate()/g" $script
#echo "go" >> $script
today=`date '+%Y-%m-%d'`
echo "Today is $today"
mkdir -p $today
cd $today
rm *sql
csplit -b %03d.sql ../$script '/^IF OBJECT_ID.*$/' '{*}'
use="use $db;";
sed -i "1i $use\n\n" *sql
echo "Running generated script $script ..."
for i in *sql
do
#    dos2unix $i
#    sqsh -S $server -U $user -P $password -i $i -c >$i.log 2>&1
done
echo "Completed."
