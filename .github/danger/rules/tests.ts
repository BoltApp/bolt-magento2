import { changed } from "../pr";

export default async function tests() {
  if (changed.some((f) => f.endsWith(".php")) && !changed.some((f) => f.endsWith("Test.php"))) {
    warn("Please make sure to add new test or update existing tests");
  }
}
