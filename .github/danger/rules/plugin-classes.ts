import { changed } from "../pr";

export default async function pluginClasses() {
  if (changed.some((f) => f.toLowerCase().startsWith("plugin/"))) {
    warn("Plugin directory has been modified - please take extra care reviewing overwritten core M2 classes.");
  }
}
