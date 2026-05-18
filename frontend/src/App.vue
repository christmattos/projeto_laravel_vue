<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const tasks = ref([]);
const newTask = ref("");

async function loadTasks() {
    try {
        const response = await axios.get("http://127.0.0.1:8000/api/tasks");
        tasks.value = response.data;
    } catch (error) {
        console.error(error);
    }
}

async function createTask() {
    if (newTask.value.trim() === "") {
        return;
    }

    try {
        await axios.post("http://127.0.0.1:8000/api/tasks", {
            title: newTask.value,
            done: false,
        });

        newTask.value = "";

        loadTasks();
    } catch (error) {
        console.error(error);
    }
}

onMounted(() => {
    loadTasks();
});
</script>

<template>
    <div style="padding: 20px">
        <h1>Lista de Tasks</h1>

        <div style="margin-bottom: 20px">
            <input v-model="newTask" placeholder="Digite uma task" />

            <button @click="createTask">Criar</button>
        </div>

        <div v-if="tasks.length === 0">Nenhuma task encontrada</div>

        <ul v-else>
            <li v-for="task in tasks" :key="task.id">
                {{ task.title }}
            </li>
        </ul>
    </div>
</template>
