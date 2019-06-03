import yaml
import sys
import re
"""
Edits docker-compose.yml to use containers based off of the specified php version
"""
data_map = {}

with open('docker-compose.yml') as yml_file:  
    data_map = yaml.safe_load(yml_file)
    new_php_version = sys.argv[1]
    fpm_image = data_map["services"]["fpm"]["image"]
    old_php_version = re.findall("\d+\.\d+", fpm_image)[0]
    data_map["services"]["fpm"]["image"] = data_map["services"]["fpm"]["image"].replace(old_php_version, new_php_version)
    data_map["services"]["build"]["image"] = data_map["services"]["build"]["image"].replace(old_php_version, new_php_version)
    data_map["services"]["deploy"]["image"] = data_map["services"]["deploy"]["image"].replace(old_php_version, new_php_version)
    data_map["services"]["cron"]["image"] = data_map["services"]["cron"]["image"].replace(old_php_version, new_php_version)
    data_map["services"]["cron"]["volumes"][1] = data_map["services"]["cron"]["volumes"][1][:-3]


with open('docker-compose.yml', "w") as output_file:
    yaml.dump(data_map, output_file, default_flow_style=False)
