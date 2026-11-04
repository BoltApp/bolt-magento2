// PR rules, one per file in rules/.
// Run by .github/workflows/danger.yml from the base branch, so a PR cannot edit its own rules.
// Rule files use Danger's globals (danger, warn, fail, message): only this file may import "danger".
import { schedule } from "danger";
import changelog from "./rules/changelog";
import helperConfig from "./rules/helper-config";
import pluginClasses from "./rules/plugin-classes";
import tests from "./rules/tests";

schedule(Promise.all([changelog, helperConfig, pluginClasses, tests].map((rule) => rule())));
