<script setup>

/**
 * DeleteDialog - Dialog component
 *
 * This Dialog deletes an entry.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, watch } from 'vue'
  import { router, useForm } from '@inertiajs/vue3'
  import ShortAlert from '@/Components/ShortAlert.vue'

  // Local components
  import PayloadView from '@/Components/PayloadView.vue'

// #endregion

// #region Dialog

  const modalVisible = ref(false)
  const modalResolve = ref(null)

  const open = (props) => {

    deleteForm.id = props.id
    route.value = props.route

    switch (props.route) {
      case 'info':
        title.value = 'Mitteilung'
        break;

      default:
        title.value = 'Eintrag'
        break;
    }

    payload.value = props.payload

    modalVisible.value = true
    return new Promise((resolve) => {
      modalResolve.value = resolve;
    })

  }

  const del = () => {
    deleteForm.delete(`/set-${route.value}/${deleteForm.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: [ route ] })
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

// #region Delete

  const title = ref(null)
  const payload = ref(null)

  const route = ref(null)
  const deleteForm = useForm({
    id: null,
  })

// #endregion

// #region Lifecycle

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="modalVisible" width="100%" max-width="600px"  :persistent="deleteForm.processing">
    <v-card>
      <v-card-title class="mb-n4">{{ title }}</v-card-title>
      <v-card-subtitle>Löschen</v-card-subtitle>
      <v-card-text>
        Möchtest du diesen Eintrag wirklich <b>unwiederbringlich</b> löschen?
        <v-card class="mb-2 mt-2">
          <PayloadView :payload="payload" :show-type-icon="false" style="padding: 1rem;" />
        </v-card>

        <ShortAlert ref="dialogAlert" />
      </v-card-text>
      <template v-slot:actions>
        <v-btn @click="cancel" :disabled="deleteForm.processing">Abbrechen</v-btn>
        <v-btn @click="del" :loading="deleteForm.processing"
          color="error">Löschen</v-btn>
      </template>
    </v-card>
  </v-dialog>
</template>
<style lang="scss" scoped></style>