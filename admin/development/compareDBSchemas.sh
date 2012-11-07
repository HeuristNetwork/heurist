# source database
DB1=$1
# compare database
DB2=$2

echo
echo
echo '******************* comparing '$DB1' with '$DB2' *********************'
mysql -u root -psmith18 -D $DB1 -e"show tables;" | grep -v Tables_in | while read tableName; do
echo
echo '*** comparing '$tableName' ****'
echo
mysqldump  -u root -psmith18  -d $DB1 $tableName > ~/compDBTempOut1.txt;
mysqldump  -u root -psmith18  -d $DB2 $tableName > ~/compDBTempOut2.txt;
diff -wy -I "^-- Dump complete.*$" -I "^-- Host.*$" -I "ENGINE=.*$" --suppress-common-lines ~/compDBTempOut1.txt ~/compDBTempOut2.txt;
done

