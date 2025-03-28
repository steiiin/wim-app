<script setup>

/**
 * ToolbarCheckbox - Component
 *
 * Togglebutton as checkbox variant for toolbar.
 *
 */

// #region Imports

  // Vue composables
  import { computed } from 'vue'

// #endregion
// #region Props

  const emit = defineEmits(['update:modelValue']);

  const props = defineProps({
    modelValue: {
      required: false,
      default: null,
    },
    label: {
      type: String,
      required: true,
    },
  });

  const computedModel = computed({
    get() {
      return props.modelValue
    },
    set(newValue) {
      emit('update:modelValue', !!newValue);
    }
  });

  const icon = computed(() => computedModel.value ? 'mdi-check' : 'mdi-square-rounded-outline')

// #endregion

</script>

<template>
  <v-btn-toggle v-model="computedModel"
    color="primary" variant="text" class="btn-narrow-text">
    <v-btn :prepend-icon="icon" :text="label" :value="true"></v-btn>
  </v-btn-toggle>
</template>