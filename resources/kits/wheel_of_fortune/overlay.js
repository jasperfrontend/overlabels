// Wheel of Fortune spin driver.
// Watches the data-result-at attribute on the spinner element. When it
// changes (a new pick landed), look up the result label, find its segment
// index, and animate the wheel to land with the pointer over that segment.

(function () {
  const spinner = document.getElementById('wheel-spinner');
  if (!spinner) return;

  // Order must match the segment <div> labels in the overlay HTML. To
  // customise the wheel's segments, edit BOTH this array and the segment
  // <span>s in the HTML, then update the c:wheel_spin OptionSet items via
  // the controls page so the picker stays in sync with what's drawn.
  const SEGMENTS = ['Pizza', 'Tacos', 'Sushi', 'Burger', 'Salad', 'Pasta', 'Curry', 'BBQ'];
  const SEGMENT_DEGREES = 360 / SEGMENTS.length;
  // Drama: at least 5 full rotations before settling on the landing angle.
  const FULL_ROTATIONS = 5;

  let currentRotation = 0;
  let lastResultAt = spinner.dataset.resultAt || '';

  function landingAngleFor(index) {
    // Segment 0 is centred at the top (-90deg in standard CSS), but conic-
    // gradient starts at 'from -90deg' which aligns segment 0 from -90 to
    // -45deg. Its CENTRE is at -67.5deg. Pointer is at -90deg (12 o'clock).
    // To put segment i under the pointer:
    //   rotation = -90deg - (segment_centre_at_zero + i * segment_deg)
    //   rotation = -90 - (-67.5 + 45*i)
    //   rotation = -22.5 - 45*i  (mod 360)
    const raw = -22.5 - SEGMENT_DEGREES * index;
    // Normalise to [0, 360) so the math is easier downstream.
    return ((raw % 360) + 360) % 360;
  }

  function spinTo(result) {
    const index = SEGMENTS.indexOf(result);
    if (index === -1) {
      // Unknown result - probably the OptionSet was edited but the visual
      // wasn't. Skip the animation rather than landing on a wrong segment.
      return;
    }

    const targetAngle = landingAngleFor(index);
    // Always rotate forward by at least FULL_ROTATIONS * 360. Compute the
    // smallest forward delta to land on the target angle from the current
    // rotation's modulo.
    const currentMod = ((currentRotation % 360) + 360) % 360;
    let delta = targetAngle - currentMod;
    if (delta <= 0) delta += 360;
    currentRotation += FULL_ROTATIONS * 360 + delta;

    spinner.style.transform = 'rotate(' + currentRotation + 'deg)';
  }

  // Run once on mount in case the page loads with a pre-existing result.
  const initialResult = spinner.dataset.result;
  if (initialResult) {
    const idx = SEGMENTS.indexOf(initialResult);
    if (idx !== -1) {
      currentRotation = landingAngleFor(idx);
      // No transition for the initial snap.
      const transition = spinner.style.transition;
      spinner.style.transition = 'none';
      spinner.style.transform = 'rotate(' + currentRotation + 'deg)';
      // Force a reflow before restoring the transition so the snap doesn't
      // animate.
      void spinner.offsetHeight;
      spinner.style.transition = transition;
    }
  }

  const observer = new MutationObserver((mutations) => {
    for (const m of mutations) {
      if (m.type !== 'attributes' || m.attributeName !== 'data-result-at') continue;
      const newAt = spinner.dataset.resultAt || '';
      if (newAt === lastResultAt || newAt === '') continue;
      lastResultAt = newAt;
      const result = spinner.dataset.result;
      if (result) spinTo(result);
    }
  });

  observer.observe(spinner, { attributes: true, attributeFilter: ['data-result-at'] });
})();
