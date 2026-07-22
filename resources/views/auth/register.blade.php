@extends('layouts.app')
@section('title', 'Cadastro - Carteira Financeira')
@section('content')
<div id="app" class="auth-container">
    <div class="card">
        <h2>Cadastro</h2>
        <div v-if="error" class="alert alert-error">@{{ error }}</div>
        <div v-if="success" class="alert alert-success">@{{ success }}</div>
        <form @submit.prevent="register" :class="{ loading: submitting }">
            <div class="form-group">
                <label>Nome</label>
                <input type="text" v-model="form.name" required autofocus>
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" v-model="form.email" required>
            </div>
            <div class="form-group">
                <label>CPF (apenas números)</label>
                <input type="text" v-model="form.cpf" maxlength="11" required placeholder="12345678901">
            </div>
            <div class="form-group">
                <label>Senha (mín. 8 caracteres)</label>
                <input type="password" v-model="form.password" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%" :disabled="submitting">
                @{{ submitting ? 'Cadastrando...' : 'Cadastrar' }}
            </button>
        </form>
        <div class="auth-link">
            <a href="/login">Já tem conta? Entrar</a>
        </div>
    </div>
</div>
<script>
const { createApp, ref } = Vue;
createApp({
    setup() {
        const form = ref({ name: '', email: '', cpf: '', password: '' });
        const error = ref('');
        const success = ref('');
        const submitting = ref(false);

        async function register() {
            error.value = '';
            success.value = '';
            submitting.value = true;
            try {
                const res = await fetch('/api/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(form.value)
                });
                const data = await res.json();
                if (!res.ok) {
                    const errs = data.errors;
                    if (errs) {
                        error.value = Object.values(errs).flat().join('. ');
                    } else {
                        error.value = 'Erro ao cadastrar';
                    }
                    return;
                }
                localStorage.setItem('jwt_token', data.token);
                window.location.href = '/dashboard';
            } catch (e) {
                error.value = 'Erro de conexão com o servidor';
            } finally {
                submitting.value = false;
            }
        }

        return { form, error, success, submitting, register };
    }
}).mount('#app');
</script>
@endsection
