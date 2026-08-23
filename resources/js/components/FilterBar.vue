<!-- The one filter-panel skin: bordered panel plus the responsive field grid.
     Pages drop FilterSearchInput / FilterSelect fields into the slot; spacing
     around the panel stays with the page. -->
<template>
  <div class="border border-sidebar-border bg-sidebar-accent p-4">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
      <slot />
    </div>
  </div>
</template>
