import { describe, expect, it } from 'vitest';
import { DEFAULT_STAGE, stageFor } from './designerStage';

const chatStages = [
  { w: 500, h: 800 },
  { when: { layout: 'ticker' }, w: 1920, h: 80 },
];

describe('stageFor', () => {
  it('falls back to 1920x1080 when the manifest declares no stage', () => {
    expect(stageFor([], () => '')).toEqual({ w: DEFAULT_STAGE.w, h: DEFAULT_STAGE.h });
  });

  it('uses the first entry without a condition as the default', () => {
    expect(stageFor(chatStages, (key) => (key === 'layout' ? 'bottom' : ''))).toEqual({ w: 500, h: 800 });
  });

  it('picks the entry whose condition the controls satisfy', () => {
    expect(stageFor(chatStages, (key) => (key === 'layout' ? 'ticker' : ''))).toEqual({ w: 1920, h: 80 });
  });

  it('requires every named control to match', () => {
    const stages = [
      { w: 1, h: 1 },
      { when: { a: 'x', b: 'y' }, w: 2, h: 2 },
    ];
    expect(stageFor(stages, (key) => (key === 'a' ? 'x' : 'no'))).toEqual({ w: 1, h: 1 });
    expect(stageFor(stages, (key) => (key === 'a' ? 'x' : 'y'))).toEqual({ w: 2, h: 2 });
  });

  it('lets the last matching entry win, so exceptions are listed after the default', () => {
    const stages = [
      { w: 1, h: 1 },
      { when: { a: 'x' }, w: 2, h: 2 },
      { when: { a: 'x' }, w: 3, h: 3 },
    ];
    expect(stageFor(stages, () => 'x')).toEqual({ w: 3, h: 3 });
  });

  it('never returns the when clause with the size', () => {
    expect(Object.keys(stageFor(chatStages, () => 'ticker'))).toEqual(['w', 'h']);
  });
});
