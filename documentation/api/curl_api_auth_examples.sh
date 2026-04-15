# curl_api_auth_examples.sh

```bash
#!/bin/bash

BASE="https://example.net"
DB="mydb"

echo "=== SESSION AUTH ==="

# Login (store cookies)
curl -c cookies.txt -X POST "$BASE/api/$DB/login" \
  -H "Content-Type: application/json" \
  -d '{"login":"user","password":"pass"}'

# Use session
curl -b cookies.txt "$BASE/api/$DB/records"

echo ""
echo "=== JWT AUTH ==="

# Get token
TOKEN=$(curl -s -X POST "$BASE/heurist/hserv/controller/auth.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"user","password":"pass","db":"'$DB'"}' \
  | jq -r .access_token)

echo "Token: $TOKEN"

# Use token
curl "$BASE/api/$DB/records" \
  -H "Authorization: Bearer $TOKEN"