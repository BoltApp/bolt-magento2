// PR rules ported from the retired Peril service (BoltApp/peril).
import { danger, fail, warn, schedule } from "danger";

const pr = danger.github.pr;
const changed = [...danger.git.created_files, ...danger.git.modified_files];

async function doNotSubmit() {
  for (const file of changed) {
    if (((await danger.github.utils.fileContents(file)) || "").includes("DONOTSUBMIT")) {
      fail("Your code has do not submit");
      return;
    }
  }
}

async function changelog() {
  // A bare `#changelog` line defaults to the PR title
  const body = (pr.body || "").replace(/^#changelog ?$/gm, "#changelog " + pr.title);
  if (body !== (pr.body || "")) {
    await danger.github.api.pulls.update({ ...danger.github.thisPR, pull_number: pr.number, body });
  }
  if (!/^#changelog .{10,}$/m.test(body)) {
    fail("Please add a changelog after #changelog");
  }
}

async function m2Checks() {
  if (changed.some((f) => f.endsWith(".php")) && !changed.some((f) => f.endsWith("Test.php"))) {
    warn("Please make sure to add new test or update existing tests");
  }
  if (changed.some((f) => f.toLowerCase().startsWith("plugin/"))) {
    warn("Plugin directory has been modified - please take extra care reviewing overwritten core M2 classes.");
  }
  if (changed.some((f) => f.toLowerCase().startsWith("helper/config.php"))) {
    warn("Helper/Config.php has been changed - please make sure you have updated getAllConfigSettings.");
  }
}

schedule(Promise.all([doNotSubmit(), changelog(), m2Checks()]));
