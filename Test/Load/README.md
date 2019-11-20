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