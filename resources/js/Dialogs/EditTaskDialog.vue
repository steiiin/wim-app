<script setup>

/**
 * EditTaskDialog - Dialog component
 *
 * This Dialog opens/create an Task.
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
      editForm.dueto = props.dueto ? CloneHelper.date(props.dueto) : null

    }
    else
    {

      editForm.id = null
      editForm.payload = { title: '' }
      editForm.dueto = null

    }

    visiblePanel.value = 'payload'

    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    });
  }

  const save = () => {
    if (!isFormValid.value) { return }

    editForm.post('/set-task', {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['task'] })
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

// #region EditTask

  const editForm = useForm({
    id: null,
    payload: null,
    dueto: null,
    from: null,
  })
  const isFormValid = computed(() => isPayloadValid.value && isTimingValid.value)

  const visiblePanel = ref('payload')

  const isPayloadValid = computed(() => !!editForm.payload?.title)
  const isTimingValid = computed(() => {
    return !!editForm.dueto && !isTaskPassed.value
  })

  // #region Timing

    const isTaskPassed = computed(() => !!editForm.dueto && editForm.dueto < new Date())

    const timingSnippet = computed(() => {
      if (!isTimingValid.value) { return '' }
      return `${DateHelper.formatDate(editForm.dueto)} ${DateHelper.formatTime(editForm.dueto)}`
    })

  // #endregion

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="100%" max-width="600px"  :persistent="editForm.processing">
    <v-card>
      <v-card-title class="mb-n4">Einzelne Aufgabe</v-card-title>
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
                <v-sheet class="py-2 px-4 mt-2">

                  <DateTimeEdit v-model="editForm.dueto" mode="datetime"
                    title="Zu erledigen bis:" class="mb-3">
                  </DateTimeEdit>

                  <v-alert v-if="isTaskPassed"
                    type="error" variant="tonal" class="my-2">
                    Deine Aufgabe soll bereits erledigt sein. Überprüfe die Daten.
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