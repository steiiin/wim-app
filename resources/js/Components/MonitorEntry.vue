<template>
  <PayloadView :payload="item" />
</template>

<script setup>

// #region Imports

  // 3rd-party composables
  import PayloadView from '@/Components/PayloadView.vue';

// #endregion

// #region Props

  const props = defineProps({
    item: {
      type: Object,
      required: true,
    },
  })

// #endregion
</script>