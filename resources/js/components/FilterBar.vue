<!-- The one filter-panel skin: bordered panel plus the responsive field grid.
     Pages drop FilterSearchInput / FilterSelect fields into the slot; spacing
     around the panel stays with the page. The desktop grid is always five
     columns so a field appearing or disappearing (like the alert-only
     Assignment filter) never resizes the fields around it - pages with fewer
     fields just leave trailing columns empty. -->
<template>
  <div class="border border-sidebar-border bg-sidebar-accent p-4">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
      <slot />
    </div>
  </div>
</template>
