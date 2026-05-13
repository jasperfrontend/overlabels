/* Intentionally empty.

   Earlier versions of this kit drove the spin with a MutationObserver and
   inline JS, but the overlay HtmlSanitizationService strips <script> tags
   on save, and even if it didn't, JS in overlays is a security boundary we
   don't want to cross.

   The animation now lives entirely in the recipe layer: the wheel_spin
   manifest ships a `rotation_deg` expression control that computes a
   monotonically-increasing target angle from the picker's result_at and
   result_index. The overlay HTML binds that value into a style="transform:
   rotate(...)" attribute, and a CSS transition on .wheel-spinner handles
   the visual interpolation. No JavaScript in the overlay layer at all. */
