<script setup>
    import { ref, onMounted } from "vue";
    import axios from "axios";

    const tasks = ref([]);
    const newTask = ref("");
    const selectedImages = ref([]);
    const fileInput = ref(null);
    const imagePreviews = ref([]);

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
            const formData = new FormData();
            formData.append("title", newTask.value);
            formData.append("done", false);

            if (selectedImages.value.length > 0) {
                for (let i = 0; i < selectedImages.value.length; i++) {
                    formData.append("images[]", selectedImages.value[i]);
                }
            }

            await axios.post("http://127.0.0.1:8000/api/tasks", formData, {
                headers: {
                    "Content-Type": "multipart/form-data"
                }
            });
    
            newTask.value = "";
            selectedImages.value = [];
            imagePreviews.value = [];

            if (fileInput.value) {
                fileInput.value.value = "";
            }

            loadTasks();
        } catch (error) {
            console.error(error);
        }
    }
    
    async function deleteTask(id) {
        try {
            await axios.delete(`http://127.0.0.1:8000/api/tasks/${id}`);
            loadTasks();
        } catch (error) {
            console.error(error);
        }
    }
    
    async function updateTask(id) {
        try {
            await axios.put(`http://127.0.0.1:8000/api/tasks/${id}`, {
                done: true
            });
            loadTasks();
        } catch (error) {
            console.error(error);
        }
    }

    function handleImages(event) {
        selectedImages.value = event.target.files;
        imagePreviews.value = [];
        for (let i = 0; i < selectedImages.value.length; i++) {
            const file = selectedImages.value[i];
            imagePreviews.value.push(
                URL.createObjectURL(file)
            );
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
            <input v-model="newTask" placeholder="Digite uma task" style="margin-right: 10px"/>
            <input ref="fileInput" type="file" multiple @change="handleImages"/>
            <button @click="createTask">
                Criar
            </button>
        </div>
        <div v-if="imagePreviews.length > 0" style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
            <img v-for="(preview, index) in imagePreviews" :key="index" :src="preview" style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px;"/>
        </div>

        <div v-if="tasks.filter(task => !task.done).length === 0">
            Nenhuma task encontrada
        </div>

        <ul v-else>
            <li v-for="task in tasks.filter(task => !task.done)" :key="task.id" style="margin-left: -25px;" >
                {{ task.title }}
                <div v-if="task.images.length > 0" style="display: flex; gap: 10px; margin-top: 10px;">
                    <img v-for="image in task.images" :key="image.id" :src="'http://127.0.0.1:8000/storage/' + image.image" style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px;"/>
                </div>
                <button @click="deleteTask(task.id)" style="margin-left: 10px;">
                    Deletar
                </button>
                <button @click="updateTask(task.id)" style="margin-left: 10px;">
                    Concluída
                </button>
            </li>
        </ul>

        <!-- aqui vai ficar o separador  -->

        <div v-if="tasks.filter(task => task.done).length === 0">
            Nenhuma task concluída
        </div>

        <ul v-else>
            <li v-for="task in tasks.filter(task => task.done)" :key="task.id" style="margin-left: -25px;">
                {{ task.title }}
                <button @click="deleteTask(task.id)" style="margin-left: 10px;">
                    Deletar
                </button>
            </li>
        </ul>
    </div>
</template>
