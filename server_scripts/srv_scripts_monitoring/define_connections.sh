# define_connections.sh
# include this file into all the scripts which operate on mysql databases and gemerate result files
# (db monitoring and summarisation, backup and restore)

# Connection to the MySQL database. If on a single tier system omit the H (Host) specification and P (Port)
# You may also be able to use local without password

connection="mysql -h<SERVERNAME> -uheurist -p<PASSWORD>"

servername="<ENTER THE SERVER NAME>"

now=$(date)

if ! [ $(id -u) = 0 ]; then
   echo "The script need to be run as root or sudo." >&2
   exit 1
fi

echo "Processing ..."

respath="/srv/scripts/RESULTS"

#mkdir will do nothing if it already exists
mkdir $respath
cd $respath
