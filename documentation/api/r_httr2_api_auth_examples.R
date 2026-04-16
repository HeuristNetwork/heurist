library(httr2)

base_api <- "https://example.net/api"
auth_url <- "https://example.net/heurist/hserv/controller/auth.php"
db <- "mydb"

cat("=== JWT AUTH ===\n")

# Get token
auth_resp <- request(auth_url) |>
  req_method("POST") |>
  req_body_json(list(
    username = "user",
    password = "pass",
    db = db
  )) |>
  req_perform()

auth_data <- resp_body_json(auth_resp)
token <- auth_data$access_token

cat("Token:", token, "\n")

# Call API
api_resp <- request(sprintf("%s/%s/records", base_api, db)) |>
  req_auth_bearer_token(token) |>
  req_perform()

print(resp_body_json(api_resp))

cat("\n=== SESSION AUTH (basic example) ===\n")

# NOTE: httr2 does not automatically persist cookies between separate requests.
# For production use, consider using curl handles or JWT instead.