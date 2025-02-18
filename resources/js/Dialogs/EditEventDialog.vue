<script setup>

/**
 * EditEventDialog - Dialog component
 *
 * This Dialog opens/create an event.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, watch } from 'vue'
  import { router, useForm } from '@inertiajs/vue3'

  // Local components
  import ShortAlert from '@/Components/ShortAlert.vue'
  import PayloadEdit from '@/Components/PayloadEdit.vue'
  import PayloadView from '@/Components/PayloadView.vue'
  import DateTimeEdit from '@/Components/DateTimeEdit.vue'
  import ToolbarCheckbox from '@/Components/ToolbarCheckbox.vue'

  // Local composables
  import { DateHelper } from '@/Utils/DateHelper';
  import { CloneHelper } from '@/Utils/CloneHelper'

// #endregion

// #region Dialog

  const modalVisible = ref(false)
  const modalResolve = ref(null)

  const open = (props) => {

    if (props?.edit)
    {

      editForm.id = props.id
      editForm.payload = CloneHelper.payload(props.payload)

      isRange.value = !!props.until
      editForm.start = props.start ? CloneHelper.date(props.start) : null
      editForm.until = props.until ? CloneHelper.date(props.until) : null
      editForm.is_allday = props.is_allday

    }
    else
    {

      editForm.id = null
      editForm.payload = { title: '' }

      isRange.value = false
      editForm.start = null
      editForm.until = null
      editForm.is_allday = true

    }

    visiblePanel.value = 'payload'

    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    });
  }

  const save = () => {
    if (!isFormValid.value) { return }

    editForm.post('/set-event', {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['event'] })
        modalResolve.value(true)
        modalVisible.value = false
      },
      onError: (errors) => {
        dialogAlert.value.showMessage(JSON.stringify(errors))
      },
    })

  }

  const cancel = () => {
    modalResolve.value(false)
    modalVisible.value = false
  }

// #endregion
// #region Alert

  const dialogAlert = ref(null)

// #endregion

// #region EditEvent

  const editForm = useForm({
    id: null,
    payload: null,
    start: null,
    until: null,
    is_allday: true,
  })
  const isFormValid = computed(() => isPayloadValid.value && isTimingValid.value)

  const visiblePanel = ref('payload')

  const isPayloadValid = computed(() => !!editForm.payload?.title)
  const isTimingValid = computed(() => {
    if (!editForm.start || isEventPassed.value) { return false }
    if (isRange.value) {
      return !!editForm.until && !isUntilBeforeStart.value
    }
    return true
  })

  // #region Timing

    const isRange = ref(true)

    const isEventPassed = computed(() => {
      if (isRange.value)
      {
        return !!editForm.until && editForm.until < new Date()
      }
      else
      {
        if (!editForm.start) { return false }
        if (editForm.is_allday) {
          const eod = new Date(editForm.start.getFullYear(), editForm.start.getMonth(), editForm.start.getDate(), 23, 59, 59, 999)
          return eod < new Date()
        } else {
          return editForm.start < new Date()
        }
      }
    })
    const isUntilBeforeStart = computed(() => !!editForm.until && !!editForm.start && editForm.start >= editForm.until)

    watch(
      () => isRange.value,
      () => {
        editForm.until = null
      }
    )

  // #endregion

  const dtEditMode = computed(() => editForm.is_allday ? 'date' : 'datetime')

  const timingSnippet = computed(() => {
    if (!isTimingValid.value) { return '' }

    const formattedStartDate = DateHelper.formatDate(editForm.start)
    if (isRange.value)
    {
      const formattedEndDate = DateHelper.formatDate(editForm.until)
      return editForm.is_allday
      ? `${formattedStartDate} bis ${formattedEndDate}`
      : `${formattedStartDate} ${DateHelper.formatTime(editForm.start)} bis ${formattedEndDate} ${DateHelper.formatTime(editForm.until)}`
    }
    else
    {
      return editForm.is_allday
      ? formattedStartDate
      : `${formattedStartDate} ${DateHelper.formatTime(editForm.start)}`
    }
  })

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="100%" max-width="600px"  :persistent="editForm.processing">
    <v-card>
      <v-card-title class="mb-n4">Termin</v-card-title>
      <v-card-subtitle>{{ !editForm.id ? 'Erstellen' : 'Bearbeiten' }}</v-card-subtitle>
      <v-card-text>
        <v-form :disabled="editForm.processing" @submit.prevent="save" validate-on="input">

          <v-card class="mb-2">
            <PayloadView :payload="editForm.payload" :show-type-icon="false" style="padding: 1rem;" />
          </v-card>

          <v-expansion-panels v-model="visiblePanel"
            :eager="true" :mandatory="true" :static="true" variant="accordion">
            <v-expansion-panel title="Inhalt" color="black" :rounded="0" value="payload">
              <v-expansion-panel-text>
                <PayloadEdit v-model="editForm.payload" class="pa-2" />
              </v-expansion-panel-text>
            </v-expansion-panel>
            <v-expansion-panel color="black" :rounded="0" value="timing" :disabled="!isPayloadValid">
              <v-expansion-panel-title v-slot="{ expanded }">
                <v-row no-gutters>
                  <v-col class="d-flex justify-start" cols="2">
                    Zeiten{{ expanded || !isTimingValid ? '' : ':' }}
                  </v-col>
                  <v-col style="opacity: .6" cols="10">
                    <v-row v-if="!expanded" no-gutters>
                      <v-col class="d-flex justify-start">
                        {{ timingSnippet }}
                      </v-col>
                    </v-row>
                  </v-col>
                </v-row>
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-sheet class="ma-2">

                  <v-toolbar density="compact">
                    <v-btn-toggle v-model="isRange" mandatory :disabled="editForm.processing"
                      color="primary" variant="text" class="btn-narrow-text">
                      <v-btn prepend-icon="mdi-calendar-today" :value="false" text="Fester Termin"></v-btn>
                      <v-btn prepend-icon="mdi-calendar-week" :value="true" text="Zeitspanne"></v-btn>
                    </v-btn-toggle>
                    <v-spacer></v-spacer>
                    <ToolbarCheckbox v-model="editForm.is_allday" label="Ganztägig" />
                  </v-toolbar>

                </v-sheet>
                <v-sheet class="pt-2 pb-4 px-4">

                  <DateTimeEdit v-model="editForm.start" :mode="dtEditMode" admode="startofday"
                    :title="isRange ? 'Startet ab:' : ''" class="mb-3">
                  </DateTimeEdit>
                  <DateTimeEdit v-model="editForm.until" :mode="dtEditMode" admode="endofday" v-if="isRange"
                    title="Endet:">
                  </DateTimeEdit>

                  <v-alert v-if="isUntilBeforeStart"
                    type="error" variant="tonal" class="my-2">
                    Dein Termin endet vorm Start. Überprüfe die Daten.
                  </v-alert>
                  <v-alert v-else-if="isEventPassed"
                    type="error" variant="tonal" class="my-2">
                    Dein Termin ist bereits vergangen. Überprüfe die Daten.
                  </v-alert>

                </v-sheet>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>

        </v-form>

        <ShortAlert ref="dialogAlert" />
      </v-card-text>
      <template v-slot:actions>
        <v-btn @click="cancel" :disabled="editForm.processing">Abbrechen</v-btn>
        <v-btn @click="save" :disabled="!isFormValid" :loading="editForm.processing"
          color="success">Speichern</v-btn>
      </template>
    </v-card>
  </v-dialog>
</template>
<style lang="scss" scoped></style>