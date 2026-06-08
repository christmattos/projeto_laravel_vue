<script setup>
    import { ref, onMounted, computed } from "vue";
    import axios from "axios";

    const tasks = ref([]);
    const newTask = ref("");
    const selectedImages = ref([]);
    const fileInput = ref(null);
    const imagePreviews = ref([]);
    const selectedTasks = ref([]);
    const uploadImages = ref([]);
    const pendingTasks = computed(() => tasks.value.filter(t => !t.done))
    const editingTask = ref(null);
    const editTitle = ref("");
    const editImages = ref([]);

    async function loadTasks() {
        try {
            const response = await axios.get("http://127.0.0.1:8000/api/tasks");
            tasks.value = response.data;
        } catch (error) {
            console.log(error.response?.data);
        }
    }
    
    async function createTask() {
        if (newTask.value.trim() === "") {
            return;
        }
        try {
            const formData = new FormData();
            formData.append("title", newTask.value);

            if (uploadImages.value.length > 0) {
                for (let i = 0; i < uploadImages.value.length; i++) {
                    formData.append("images[]", uploadImages.value[i]);
                }
            }

            await axios.post("http://127.0.0.1:8000/api/tasks", formData, {
                headers: {
                    "Content-Type": "multipart/form-data"
                }
            });
    
            newTask.value = "";
            uploadImages.value = [];
            imagePreviews.value = [];

            if (fileInput.value) {
                fileInput.value.value = "";
            }

            loadTasks();
        } catch (error) {
            console.log(error.response?.data);
        }
    }
    
    async function deleteTask(id) {
        try {
            await axios.delete(`http://127.0.0.1:8000/api/tasks/${id}`);
            loadTasks();
        } catch (error) {
            console.log(error.response?.data);
        }
    }
    
    async function updateTask(id) {
        try {
            await axios.put(`http://127.0.0.1:8000/api/tasks/${id}`, {
                done: true
            });
            loadTasks();
        } catch (error) {
            console.log(error.response?.data);
        }
    }

    function handleImages(event) {
        uploadImages.value = Array.from(event.target.files);
        imagePreviews.value = [];
        for (let i = 0; i < uploadImages.value.length; i++) {
            const file = uploadImages.value[i];
            imagePreviews.value.push(
                URL.createObjectURL(file)
            );
        }
    }

    async function deleteSelectedTasks() {
        try {
            await Promise.all(
                selectedTasks.value.map(id =>
                axios.delete(`http://127.0.0.1:8000/api/tasks/${id}`)
                )
            )
            selectedTasks.value = [];
            loadTasks();
        } catch (error) {
            console.log(error.response?.data);
        }
    }

    async function deleteSelectedImages() {
        try {
            for (const imageId of selectedImages.value) {
                await axios.delete(`http://127.0.0.1:8000/api/images/${imageId}`);
            }
            selectedImages.value = [];
            loadTasks();
        } catch (error) {
            console.log(error.response?.data);
        }
    }

    function startEdit(task) {
        editingTask.value = task.id;
        editTitle.value = task.title;
    }

    function handleEditImages(event) {
        editImages.value = Array.from(event.target.files);
    }

    async function saveEdit(taskId) {
        try {
            const formData = new FormData();

            formData.append("title", editTitle.value);

            for (const image of editImages.value) {
                formData.append("images[]", image);
            }

            await axios.post(
                `http://127.0.0.1:8000/api/tasks/${taskId}?_method=PUT`,
                formData,
                {
                    headers: {
                        "Content-Type": "multipart/form-data"
                    }
                }
            );

            editingTask.value = null;
            editTitle.value = "";
            editImages.value = [];

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
            <li v-for="task in pendingTasks" :key="task.id" style="margin-left: -25px;" >
                <input type="checkbox" :value="task.id" v-model="selectedTasks"/>
                <template v-if="editingTask === task.id">
                    <input v-model="editTitle" />
                    <input
                        type="file"
                        multiple
                        @change="handleEditImages"
                    />
                    <button @click="saveEdit(task.id)">
                        Salvar
                    </button>
                    <button @click="editingTask = null">
                        Cancelar
                    </button>
                </template>
                <template v-else>
                    {{ task.title }}
                    <button @click="startEdit(task)">
                        Editar
                    </button>
                </template>
                <div v-if="task.images?.length > 0" style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                    <div v-for="image in task.images" :key="image.id" style="display: flex; flex-direction: column; align-items: center;">
                        <input type="checkbox" :value="image.id" v-model="selectedImages"/>
                        <img :src="'http://127.0.0.1:8000/storage/' + image.image" style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px;"/>
                    </div>
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
                <input type="checkbox" :value="task.id" v-model="selectedTasks"/>
                {{ task.title }}
                <button @click="deleteTask(task.id)" style="margin-left: 10px;">
                    Deletar
                </button>
            </li>
        </ul>
        <button @click="deleteSelectedTasks">
            Deletar Tasks Selecionadas
        </button>
        <button @click="deleteSelectedImages">
            Deletar Imagens
        </button>
    </div>
</template>
