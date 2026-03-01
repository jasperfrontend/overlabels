# Investigate this, then add it to milestone 2 and 3:

An external integration like Ko-fi should, apart from being a new way to generate alerts, also expose its current values (goals, latest X, top Y -- whatever you can get from their API) as Overlabels Controls. That way users can use these values in their static templates, for example:
```html
<div class="ko-fi-goal">
We're currently at [[[c:kofi:kofis_received]]] / [[[c:kofi_goal]]] received ko-fi's! <strong>Help reach the goal! Type !kofi in chat</strong>
</div>
````
In this example `c:kofi:x` gets data from an external service, while `c:kofi_goal` is just a set arbitrary numeric Control value.

Then whenever a new ko-fi payload is received, it should update these Controls' values with 
the latest received data. Looking at Milestone 2 and 3 in `docs/MILESTONES`, building the 
external systems to also include the Controls (see route `/help/controls`) opens up a 
whole new world of possibilities -- as Controls already work in CSS, in the comparison 
engine and basically everywhere.

Imagine if a user could do `[[[if:c:kofi:kofis_received => 1000]]]We hit 1000 Kofi donations![[[endif]]]` or use it in their CSS 
just like they can with other Controls like the text, number, timer, datetime and boolean values we already have. I want external 
systems to be persistent through Controls AND generate alerts as well. This way users can use these values in their static overlay 
templates through Controls, as well as in their alerts, just as they can with their follower count, subscriber count, etc etc. 
(the syntax would be slightly dreadful with `if:control:externalservice:payload` but that's how it is. 
`[[[if:c:kofi` still reads as logical to me – and it's extensible with other services like `[[[if:c:throne...`

1. What do you think of this idea of allowing external services to populate Controls so users can use these as values in their static overlays? Are there any concerns?
2. Research the possibilities of updating Controls values whenever an API payload from an external service updates and how much control a user 
should get over that, keeping data integrity in mind. We don't want users to be able to manually adjust their donation count or whatever.
3. Research extending the current Twitch EventSub-based alert system built into the system to allow for extensions by third-party providers 
like ko-fi, and later Patreon, buymeacoffee and other providers as mentioned in docs/ms2-research

Build a complete plan to integrate external services, normalise it as described in the research document and integrate ko-fi into Alerts as 
well as Controls. Don't write code yet, but write the plan to make MS 2 and 3 happen. Keep future extensions in mind.
