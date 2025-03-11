<script setup>

/**
 * ModuleSharepointDialog - Dialog component
 *
 * This Dialog changes the Sharepoint module settings.
 *
 */

// #region Imports

// Vue composables
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ShortAlert from '@/Components/ShortAlert.vue'
import { CloneHelper } from '@/Utils/CloneHelper'
import { RuleHelper } from '@/Utils/RuleHelper'

// #endregion

// #region Dialog

  const modalVisible = ref(false)
  const modalResolve = ref(null)

  const open = (props) => {

    settingsForm.username = CloneHelper.string(props.username)
    settingsForm.password = ''
    settingsForm.sharepoint_link = CloneHelper.string(props.sharepoint_link)
    settingsForm.branch = (!props.username && !props.sharepoint_link) ? 'all' : 'credentials'

    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    });

  }

  const save = () => {
    if (!isFormValid.value) { return }

    settingsForm.post('/set-module-sharepoint', {
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

// #region SharepointSettings

  const settingsForm = useForm({
    branch: 'credentials',
    username: '',
    password: '',
    sharepoint_link: '',
  });
  const isCredentialsFormValid = ref(false)
  const isLinkFormValid = ref(false)

  const isFormValid = computed(() => {
    if (settingsForm.branch === 'all') {
      return isCredentialsFormValid.value && isLinkFormValid.value
    } else if (showCredentialSheet.value) {
      return isCredentialsFormValid.value
    } else if (showLinkSheet.value) {
      return isLinkFormValid.value
    } else {
      return false
    }
  })

  const showSelector = computed(() => settingsForm.branch !== 'all')
  const showCredentialSheet = computed(() => settingsForm.branch !== 'link')
  const showLinkSheet = computed(() => settingsForm.branch !== 'credentials')

  // validation rules
  const userRule = v => RuleHelper.empty(v)===true || 'Du musst einen Benutzernamen eintragen.'
  const passRule = v => RuleHelper.empty(v)===true || 'Du musst ein Passwort eintragen.'

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="auto" :persistent="settingsForm.processing">
    <v-card style="min-width: 400px;">
      <v-card-title class="mb-n4">Sharepoint</v-card-title>
      <v-card-subtitle>Einstellungen</v-card-subtitle>
      <v-card-text>
        <v-sheet v-if="showSelector">
          <v-toolbar density="compact">
            <v-btn-toggle v-model="settingsForm.branch" mandatory :disabled="settingsForm.processing"
              color="primary" variant="text" class="btn-narrow-text">
              <v-btn prepend-icon="mdi-lock" value="credentials" text="Zugangsdaten"></v-btn>
              <v-btn prepend-icon="mdi-link-variant" value="link" text="Adresse"></v-btn>
            </v-btn-toggle>
          </v-toolbar>
        </v-sheet>
        <v-form v-if="showCredentialSheet"
          v-model="isCredentialsFormValid" :disabled="settingsForm.processing" @submit.prevent="save" validate-on="input"
          class="mt-4">
          <v-text-field v-model="settingsForm.username" :rules="[userRule]"
            label="Benutzername">
          </v-text-field>
          <v-text-field v-model="settingsForm.password" :rules="[passRule]" type="password"
            label="Passwort">
          </v-text-field>
        </v-form>
        <v-form v-if="showLinkSheet"
          v-model="isLinkFormValid" :disabled="settingsForm.processing" @submit.prevent="save" validate-on="input"
          class="mt-4">
          <v-text-field v-model="settingsForm.sharepoint_link" :rules="[RuleHelper.link]"
            label="Sharepointadresse">
          </v-text-field>
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