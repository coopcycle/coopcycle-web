#!/bin/sh
set -e

# The healthcheck can pass before the S3 API accepts requests
until aws s3api list-buckets > /dev/null 2>&1; do
  sleep 1
done

for bucket in edifact exports images; do
  aws s3api create-bucket --bucket "$bucket" > /dev/null
done

# Anonymous read-only access, like `mc anonymous set download`
aws s3api put-bucket-policy --bucket images --policy file:///rustfs/images-policy.json
