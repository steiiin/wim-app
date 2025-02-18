<script setup>

/**
 * DateTimeEdit - Component
 *
 * Date & Time picker.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, watch } from 'vue'

// #endregion
// #region Props

  const emit = defineEmits(['update:modelValue']);

  const props = defineProps({
    modelValue: {
      required: false,
      default: null,
    },
    title: {
      type: String,
      default: 'Datum & Zeit wählen',
    },
    mode: {
      validator(value, props) {
        return ['datetime', 'date', 'time'].includes(value)
      },
      default: 'datetime',
    },
    admode: {
      validator(value, props) {
        return ['startofday', 'endofday'].includes(value)
      },
      default: 'startofday',
    }
  });

  const showDate = computed(() => props.mode.includes('date'))
  const showTime = computed(() => props.mode.includes('time'))

  const localDate = ref(null);
  const localTime = ref(null);

  const dateToDateInput = (date) => {
    if (!date) { return null }
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    return `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`
  }

  const dateToTimeInput = (date) => {
    return date?.toTimeString().split(' ')[0].substring(0, 5) ?? null;
  }

  watch(
    () => props.modelValue,
    (newVal) => {

      if ((!!localDate.value || !!localTime.value) && (!localDate.value || !localTime.value))
      {
        return
      }

      if (props.mode === 'time')
      {
        localTime.value = dateToTimeInput(newVal)
        localDate.value = null
      }
      else if (props.mode === 'date')
      {
        localTime.value = null
        localDate.value = dateToDateInput(newVal)
      }
      else if (props.mode === 'datetime')
      {
        localTime.value = dateToTimeInput(newVal)
        localDate.value = dateToDateInput(newVal)
      }

    },
    { immediate: true }
  )

  watch(
    [localDate, localTime],
    ([newDate, newTime]) => {

      if (props.mode === 'time')
      {
        const baseDate = new Date(3000, 0, 1)
        if (!!newTime && newTime.includes(':')) {
          const [hours, minutes] = newTime.split(':')
          baseDate.setHours(hours, minutes, 0, 0)
          emit('update:modelValue', baseDate)
          return
        }
      }

      else
      {
        const parsedDate = new Date(newDate)
        if (!!newDate && parsedDate instanceof Date && !isNaN(parsedDate))
        {

          if (props.mode === 'datetime')
          {
            if (!!newTime && newTime.includes(':')) {
              const [hours, minutes] = newTime.split(':')
              parsedDate.setHours(hours, minutes, 0, 0)
              emit('update:modelValue', parsedDate)
              return
            }
          }

          else if (props.mode === 'date')
          {

            if (props.admode === 'startofday') {
              parsedDate.setHours(0, 0, 0, 0)
            } else if (props.admode === 'endofday') {
              parsedDate.setHours(23, 59, 59, 999)
            }

            emit('update:modelValue', parsedDate)
            return

          }

        }
      }

      emit('update:modelValue', null)

    }
  )

  watch(
    () => props.mode,
    (newMode, oldMode) => {

      if (oldMode === 'date' && newMode === 'datetime') {

        localTime.value = null
        emit('update:modelValue', null)

      }
      else if (oldMode === 'datetime' && newMode === 'date') {

        if (!localDate.value) {
          emit('update:modelValue', null)
          return
        }

        const parsedDate = new Date(localDate.value)
        if (props.admode === 'startofday') {
          parsedDate.setHours(0, 0, 0, 0)
        } else if (props.admode === 'endofday') {
          parsedDate.setHours(23, 59, 59, 999)
        }
        emit('update:modelValue', parsedDate)

      }

    }
  )

// #endregion

</script>

<template>
  <div class="dtedit">
    <div class="dtedit-title">{{ title }}</div>
    <div class="dtedit-row">
      <div v-if="showDate" class="date">
        <v-text-field v-model="localDate" type="date" hide-details>
        </v-text-field>
      </div>
      <div v-if="showTime" class="time">
        <v-text-field v-model="localTime" type="time" hide-details
          append-inner-icon="mdi-clock-time-four-outline">
        </v-text-field>
      </div>
    </div>
  </div>
</template>
<style lang="css">
.dtedit {

  .v-field__input,
  .v-field__input input {
    text-align: center;
  }
}
</style>
<style lang="scss" scoped>
.dtedit {
  &-title {
    font-size: 0.9rem;
    font-weight: 600;
  }

  &-row {
    display: flex;
    gap: 1rem;

    &>div {
      flex: 1;
    }

    &>.date {
      max-width: 180px;
    }

    &>.time {
      max-width: 120px;
    }
  }
}
</style>