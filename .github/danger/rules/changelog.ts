export default async function changelog() {
  const pr = danger.github.pr;
  // A bare `#changelog` line defaults to the PR title
  const body = (pr.body || "").replace(/^#changelog ?$/gm, "#changelog " + pr.title);
  if (body !== (pr.body || "")) {
    await danger.github.api.pulls.update({ ...danger.github.thisPR, pull_number: pr.number, body });
  }
  if (!/^#changelog .{10,}$/m.test(body)) {
    fail("Please add a changelog after #changelog");
  }
}
