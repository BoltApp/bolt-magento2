import json
import sys
"""
This script adds the bolt repository dependencies to the compose file needed for installation 
"""


data = {}
with open('../../magento-cloud/composer.json') as json_file:  
    data = json.load(json_file)
    repositories = []
    try:
        composer = data["repositories"]["repo"]
        repositories.append(composer)
    except:
        repositories = data["repositories"]
    bolt_repo = {"type": sys.argv[3], "url": sys.argv[2]}
    if bolt_repo not in repositories:
        repositories.append(bolt_repo)
        data["repositories"] = repositories
    data["require"]["boltpay/bolt-magento2"] = sys.argv[1]

with open('../../magento-cloud/composer.json', 'w') as outfile:  
    json.dump(data, outfile, indent=4)