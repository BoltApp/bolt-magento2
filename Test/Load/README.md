Bolt [k6](https://github.com/loadimpact/k6) load testing scripts.

## Writing Tests

https://docs.k6.io/docs

k6 execution environment is not nodejs (for performance reasons).
Code written for it has to follow certain guidelines:

- vanilla JS (no TS)
- absolute imports only from "k6" namespace
- "./" relative imports only within this folder

## Running Tests

Setup the load test for by following the instructions in `setup-files/README.md`
Then setup the `config.js` file in this directory so that it matches your stores configuration.

Then to run the test once:

    docker run -i -v $(pwd):/src -e NODE_PATH=/src loadimpact/k6 run \
      /src/user_flow.js

To specify number of users/duration:

    docker run -i -v $(pwd):/src -e NODE_PATH=/src loadimpact/k6 run \
      --vus 10 --duration 60s /src/user_flow.js

Debugging http:
    docker run -i loadimpact/k6 run \
      --http-debug /src/user_flow.js

## Creating BlackFire Profiles 
Follow these steps to setup [Blackfire](https://www.notion.so/boltteam/Blackfire-17f549490e084c7a98097d7b37d2d0fd)

Create profiles by running: 

    blackfire --samples 3 run sh -c \
      'k6 run user_flow.js \
      -e SLEEP=0 \
      -e BLACKFIRE_HEADER=$BLACKFIRE_QUERY'
