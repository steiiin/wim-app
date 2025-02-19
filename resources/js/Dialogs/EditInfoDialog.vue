<script setup>

/**
 * EditInfoDialog - Dialog component
 *
 * This Dialog opens/create an information.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, watch } from 'vue'
  import { router, useForm } from '@inertiajs/vue3'
  import ShortAlert from '@/Components/ShortAlert.vue'
  import PayloadEdit from '@/Components/PayloadEdit.vue'
  import PayloadView from '@/Components/PayloadView.vue'
  import DateTimeEdit from '@/Components/DateTimeEdit.vue'

  // Local components
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
      editForm.is_permanent = props.is_permanent
      editForm.from = props.from ? CloneHelper.date(props.from) : null
      editForm.until = props.until ? CloneHelper.date(props.until) : null

    }
    else
    {

      editForm.id = null
      editForm.payload = { title: '' }
      editForm.is_permanent = true
      editForm.from = null
      editForm.until = null

    }

    visiblePanel.value = 'payload'

    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    });
  }

  const save = () => {
    if (!isFormValid.value) { return }

    editForm.post('/set-info', {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['info'] })
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

// #region EditInfo

  const editForm = useForm({
    id: null,
    payload: null,
    is_permanent: true,
    from: null,
    until: null,
    is_allday: true,
  })
  const isFormValid = computed(() => isPayloadValid.value && isTimingValid.value)

  const visiblePanel = ref('payload')

  const isPayloadValid = computed(() => !!editForm.payload?.title)
  const isTimingValid = computed(() => (
    editForm.is_permanent || (
      editForm.from instanceof Date && !isNaN(editForm.from) &&
      editForm.until instanceof Date && !isNaN(editForm.until) &&
      !isUntilBeforeFrom.value && !isUntilPassed.value
    )
  ))

  const isUntilPassed = computed(() => !!editForm.until && editForm.until < new Date())
  const isUntilBeforeFrom = computed(() => !!editForm.from && !!editForm.until && editForm.from >= editForm.until)

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="100%" max-width="600px"  :persistent="editForm.processing">
    <v-card>
      <v-card-title class="mb-n4">Mitteilung</v-card-title>
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
                    Zeitraum{{ expanded || !isTimingValid ? '' : ':' }}
                  </v-col>
                  <v-col style="opacity: .6" cols="8">
                    <v-row v-if="!expanded" no-gutters>
                      <v-col class="d-flex justify-start" cols="6">
                        {{
                          editForm.is_permanent
                          ? 'Dauerhaft sichtbar'
                          : (
                              isTimingValid
                              ? `Von ${DateHelper.formatDate(editForm.from)}, Bis: ${DateHelper.formatDate(editForm.until)}`
                              : ''
                            )
                        }}
                      </v-col>
                    </v-row>
                  </v-col>
                </v-row>
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-sheet class="ma-2">
                  <v-toolbar density="compact">
                    <v-btn-toggle v-model="editForm.is_permanent" mandatory :disabled="editForm.processing"
                      color="primary" variant="text" class="btn-narrow-text">
                      <v-btn prepend-icon="mdi-lock" :value="true" text="Dauerhaft"></v-btn>
                      <v-btn prepend-icon="mdi-timer-sand" :value="false" text="Zeitraum festlegen"></v-btn>
                    </v-btn-toggle>
                  </v-toolbar>
                </v-sheet>
                <v-sheet v-if="!editForm.is_permanent" class="pt-2 pb-4 px-4">
                  <v-row no-gutters>
                    <v-col :style="{ maxWidth: '180px' }" class="mb-3">
                      <DateTimeEdit v-model="editForm.from" mode="date"
                        title="Sichtbar ab:">
                      </DateTimeEdit>
                    </v-col>
                    <v-col class="d-flex align-center mt-4" v-if="false">
                      <v-checkbox  label="Sofort sichtbar"></v-checkbox>
                    </v-col>
                  </v-row>

                  <DateTimeEdit v-model="editForm.until" mode="date" admode="endofday"
                    title="Verschwindet nach:">
                  </DateTimeEdit>

                  <v-alert v-if="!!editForm.from && !!editForm.until && isUntilBeforeFrom"
                    type="error" variant="tonal" class="my-2">
                    Du möchtest die Meldung erst nach ihrem Verschwinden sichtbar machen. Überprüfe die Daten.
                  </v-alert>
                  <v-alert v-else-if="!!editForm.until && isUntilPassed"
                    type="error" variant="tonal" class="my-2">
                    Deine Meldung würde sofort entfernt werden.
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