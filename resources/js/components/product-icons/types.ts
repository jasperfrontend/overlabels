/**
 * What every product icon accepts. All optional: with none set, an icon is
 * 1em square, takes the surrounding text colour and has no background, so a
 * Tailwind class (`size-7 text-violet-400`) still does everything a prop can.
 */
export interface ProductIconProps {
  /** Width and height. A number is pixels, a string is any CSS length ('2rem'). */
  size?: number | string;
  /** The mark's colour, any CSS colour. Unset = currentColor. */
  color?: string;
  /** Fills the square behind the mark, any CSS colour. Unset = transparent. */
  background?: string;
  /**
   * Space between the mark and the edge of the square, in icon units (the
   * mark is drawn on a 24-unit grid). Mostly useful with a background:
   * 4 gives the tile look of the dashboard's installed products.
   */
  padding?: number;
  /** Accessible name. Unset = decorative, hidden from screen readers. */
  title?: string;
}
