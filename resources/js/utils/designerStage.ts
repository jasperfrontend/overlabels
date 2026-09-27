/**
 * The OBS browser-source size a product's designer previews at.
 *
 * A manifest's `designer.stage` is a list of sizes: the first entry with no
 * `when` is the default, and an entry with `when` applies while every control
 * it names holds the given value. The last matching entry wins, so a manifest
 * lists the default first and the exceptions after it. A ticker in a 500x800
 * box would look nothing like a ticker; a full-screen effect in a 500x800 box
 * would be a quarter of itself.
 */
export interface StageSize {
  w: number;
  h: number;
  when?: Record<string, string>;
}

export const DEFAULT_STAGE: StageSize = { w: 1920, h: 1080 };

export function stageFor(stages: StageSize[], valueOf: (key: string) => string): { w: number; h: number } {
  let chosen: StageSize | null = null;

  for (const stage of stages) {
    if (!stage.when) {
      if (chosen === null) chosen = stage;
      continue;
    }
    if (Object.entries(stage.when).every(([key, value]) => valueOf(key) === value)) {
      chosen = stage;
    }
  }

  const { w, h } = chosen ?? DEFAULT_STAGE;
  return { w, h };
}
