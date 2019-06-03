import json
import sys

"""
This script formats the auth.json based off of the credentials provided in the config.sh
"""

data = {
    "http-basic": {
        "repo.magento.com": {
            "username": sys.argv[1], 
            "password": sys.argv[2]
        }
    }
}

with open('auth.json', 'w') as outfile:  
    json.dump(data, outfile, indent=4)