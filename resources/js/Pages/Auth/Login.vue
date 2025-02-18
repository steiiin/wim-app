<script setup>

/**
 * Login - Page component
 *
 * This page is shown if user isn't authenticated while accessing admin page.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, nextTick, onMounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // 3rd-party composables
  import { getSunrise, getSunset } from 'sunrise-sunset-js';

// #endregion
// #region Props

    defineProps({
        canResetPassword: {
            type: Boolean,
        },
        status: {
            type: String,
        },
    });

    const form = useForm({
        passphrase: '',
    });

// #endregion
// #region Navigation

    const submit = () => {
        form.post(route('login'), {
            onFinish: () => form.reset('password'),
        });
    };

// #endregion

</script>

<template>

    <Head title="Anmeldung" />
    <main id="login">
        <v-card class="login-box">

            <h1>WIM-Admin</h1>

            <form @submit.prevent="submit">

                <v-text-field
                    name="passphrase"
                    label="Passphrase"
                    variant="outlined"
                    hide-details
                    autofocus
                    type="password"
                    :disabled="form.processing"
                    v-model="form.passphrase"
                    style="margin-top: 1rem">
                </v-text-field>

                <error>{{ form.errors.passphrase }}</error>

                <div class="d-flex justify-end" style="margin-top: 1rem">
                    <v-btn type="submit" :loading="form.processing"
                        variant="tonal"
                    >Anmelden</v-btn>
                </div>

            </form>
        </v-card>
    </main>
</template>
<style lang="scss" scoped>
#login
{

    font-size: 16px !important;

    display: flex;
    height: 100%;
    justify-content: center;
    align-items: center;

    .login-box
    {
        max-width: 500px;
        padding: 1rem;
        flex: 1;

        h1
        {
            margin-bottom: .5rem;
        }

        error
        {
            display: block;
            color: red;
            font-size: 0.8rem;
            font-weight: 600;
        }

    }

}
</style>