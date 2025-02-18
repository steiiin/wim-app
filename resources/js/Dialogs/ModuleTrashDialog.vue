<script setup>

/**
 * ModuleTrashDialog - Dialog component
 *
 * This Dialog changes the trash module settings.
 *
 */

// #region Imports

// Vue composables
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ShortAlert from '@/Components/ShortAlert.vue'

// #endregion

// #region Dialog

  const modalVisible = ref(false)
  const modalResolve = ref(null)

  const open = (props) => {
    settingsForm.calendar_link = props.calendar_link;
    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    });
  }

  const save = () => {
    if (!isFormValid.value) { return }

    settingsForm.post('/set-module-trash', {
      preserveScroll: true,
      onSuccess: () => {
        modalResolve.value(true)
        modalVisible.value = false
      },
      onError: (errors) => {
        dialogAlert.value.showErrors(errors)
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

// #region StationSettings

  const settingsForm = useForm({
    calendar_link: ''
  });
  const isFormValid = ref(false)

  // validation rules
  const emptyRule = v => !!v || 'Du musst einen Namen eintragen.'
  const linkRule = v => {
    if (!/^(https?:\/\/)([\w-]+(\.[\w-]+)+)([\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-])?$/i.test(v)) {
      return 'Du musst einen gültigen Link eingeben.'
    }
    return true
  }

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="auto" :persistent="settingsForm.processing">
    <v-card style="min-width: 400px;">
      <v-card-title class="mb-n4">Abfallkalender</v-card-title>
      <v-card-subtitle>Einstellungen</v-card-subtitle>
      <v-card-text>
        <v-form :disabled="settingsForm.processing" v-model="isFormValid" @submit.prevent="save" validate-on="input">
          <v-text-field v-model="settingsForm.calendar_link" :rules="[emptyRule, linkRule]"
            label="Kalenderlink" autofocus=""></v-text-field>
          <hint>
            Füge einen Link zu einem Online-Abfallkalender ein. <br/> z.B. von:
            <a href="https://www.zaoe.de/abfallkalender/entsorgungstermine/abholtermine/" target="_blank">https://zaoe.de</a>.
          </hint>
        </v-form>
        <ShortAlert ref="dialogAlert" />
      </v-card-text>
      <template v-slot:actions>
        <v-btn @click="cancel" :disabled="settingsForm.processing">Abbrechen</v-btn>
        <v-btn @click="save" :disabled="!isFormValid" :loading="settingsForm.processing"
          color="success">Speichern</v-btn>
      </template>
    </v-card>
  </v-dialog>
</template>
<style lang="scss" scoped></style>