<script setup>

/**
 * PayloadEdit - Component
 *
 * Takes generates or edits a payload object.
 *
 */

// #region Imports

// Vue composables
import { computed } from 'vue'

// Local components
import { RuleHelper } from '@/Utils/RuleHelper'

// #endregion
// #region Props

const emit = defineEmits(['update:modelValue'])

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  }
});

const computedModel = computed({
  get() {
    return props.modelValue
  },
  set(newValue) {
    emit('update:modelValue', newValue);
  }
});

// #endregion
// #region Validation

const titleRule = v => RuleHelper.empty(v)===true || 'Du musst einen Titel angeben.'

// #endregion

</script>

<template>
  <div>
    <v-text-field v-model="computedModel.vehicle" :rules="[]"
      :counter="15" label="Besatzung" maxlength="15" hide-details
      style="max-width: 180px;" >
    </v-text-field>
    <v-text-field v-model="computedModel.title" :rules="[titleRule]"
      :counter="60" label="Titel" maxlength="60" hide-details="auto" autofocus
      class="mb-2" :rounded="0">
    </v-text-field>
    <v-text-field v-model="computedModel.meta" :rules="[]"
      :counter="30" label="Zusatzinfo (Ort, Rubrik)" maxlength="30" hide-details
      >
    </v-text-field>
    <v-textarea v-model="computedModel.description" :rules="[]"
      label="Beschreibung" maxlength="120" hide-details :rounded="0">
    </v-textarea>
  </div>
</template>
