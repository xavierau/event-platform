<template>
  <div>
    <!-- Filters -->
    <div v-if="$slots.filters" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
      <slot name="filters" />
    </div>

    <!-- Table -->
    <Table>
      <TableHeader>
        <TableRow>
          <slot name="header" />
        </TableRow>
      </TableHeader>
      <TableBody>
        <slot name="body" />
      </TableBody>
    </Table>
  </div>
</template>

<script setup lang="ts">
import { Table, TableHeader, TableRow, TableBody } from '@/components/ui/table';
</script>
