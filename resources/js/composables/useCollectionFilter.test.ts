import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useCollectionFilter } from './useCollectionFilter';

interface Row {
  name: string;
  body: string;
}

const rows: Row[] = [
  { name: 'rules', body: 'Three rules on the scooter streams.' },
  { name: 'tip', body: 'Chip in for gas here.' },
  { name: 'socials', body: 'Find me everywhere.' },
];

const byNameOrBody = (row: Row, q: string) => row.name.toLowerCase().includes(q) || row.body.toLowerCase().includes(q);

describe('useCollectionFilter', () => {
  it('returns every item while the query is empty', () => {
    const { filtered, filtering } = useCollectionFilter(() => rows, byNameOrBody);

    expect(filtering.value).toBe(false);
    expect(filtered.value).toHaveLength(3);
  });

  it('narrows to the items the predicate accepts', () => {
    const { query, filtered } = useCollectionFilter(() => rows, byNameOrBody);

    query.value = 'rul';

    expect(filtered.value.map((r) => r.name)).toEqual(['rules']);
  });

  it('finds a word that only appears in the body', () => {
    const { query, filtered } = useCollectionFilter(() => rows, byNameOrBody);

    query.value = 'scoot';

    expect(filtered.value.map((r) => r.name)).toEqual(['rules']);
  });

  it('hands the predicate a lowercased, trimmed query', () => {
    const seen: string[] = [];
    const { query, filtered } = useCollectionFilter(
      () => rows,
      (_row, q) => {
        seen.push(q);
        return true;
      },
    );

    query.value = '  RuLeS  ';
    void filtered.value;

    expect(seen).toContain('rules');
    expect(seen).not.toContain('  RuLeS  ');
  });

  it('treats a whitespace-only query as no filter', () => {
    const { query, filtering, filtered } = useCollectionFilter(() => rows, byNameOrBody);

    query.value = '   ';

    expect(filtering.value).toBe(false);
    expect(filtered.value).toHaveLength(3);
  });

  it('leaves the query as typed so the box does not fight the caret', () => {
    const { query, normalized } = useCollectionFilter(() => rows, byNameOrBody);

    query.value = '  RuLeS ';

    expect(query.value).toBe('  RuLeS ');
    expect(normalized.value).toBe('rules');
  });

  it('re-filters when the source items change', () => {
    const source = ref<Row[]>([...rows]);
    const { query, filtered } = useCollectionFilter(() => source.value, byNameOrBody);

    query.value = 'gas';
    expect(filtered.value.map((r) => r.name)).toEqual(['tip']);

    // A row deleted through Inertia re-renders the page with new props; the
    // getter has to see them rather than a snapshot taken at setup time.
    source.value = source.value.filter((r) => r.name !== 'tip');
    expect(filtered.value).toEqual([]);
  });

  it('matches nothing when the predicate rejects everything', () => {
    const { query, filtered, filtering } = useCollectionFilter(() => rows, byNameOrBody);

    query.value = 'kangaroo';

    expect(filtering.value).toBe(true);
    expect(filtered.value).toEqual([]);
  });
});
