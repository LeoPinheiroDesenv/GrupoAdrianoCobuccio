@extends('layouts.app')
@section('title', 'Login - Carteira Financeira')
@section('content')
<div id="app" class="auth-container">
    <div class="card">
        <h2>Entrar</h2>
        <div v-if="error" class="alert alert-error">@{{ error }}</div>
        <form @submit.prevent="login" :class="{ loading: submitting }">
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" v-model="form.email" required autofocus>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" v-model="form.password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%" :disabled="submitting">
                @{{ submitting ? 'Entrando...' : 'Entrar' }}
            </button>
        </form>
        <div class="auth-link">
            <a href="/register">Não tem conta? Cadastre-se</a>
        </div>
    </div>
</div>
<script>
const { createApp, ref } = Vue;
createApp({
    setup() {
        const form = ref({ email: '', password: '' });
        const error = ref('');
        const submitting = ref(false);

        async function login() {
            error.value = '';
            submitting.value = true;
            try {
                const res = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(form.value)
                });
                const data = await res.json();
                if (!res.ok) {
                    error.value = data.error || data.errors?.email?.[0] || data.errors?.password?.[0] || 'Erro ao fazer login';
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

        return { form, error, submitting, login };
    }
}).mount('#app');
</script>
@endsection
