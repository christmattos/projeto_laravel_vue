<script setup>
    import { ref, onMounted, computed, watch } from "vue";
    import axios from "axios";
    import draggable from "vuedraggable";

    const API_URL = "http://127.0.0.1:8000/api";
    const STORAGE_URL = "http://127.0.0.1:8000/storage";

    const tasks = ref([]);
    const draggedTasks = ref([]);
    const newTask = ref("");
    const selectedImages = ref([]);
    const fileInput = ref(null);
    const imagePreviews = ref([]);
    const selectedTasks = ref([]);
    const uploadImages = ref([]);
    const editingTask = ref(null);
    const editTitle = ref("");
    const editImages = ref([]);
    const isReorderingTasks = ref(false);
    const isReorderingImages = ref(false);

    const pendingTasks = computed(() => tasks.value.filter(t => !t.done));
    const completedTasks = computed(() => tasks.value.filter(t => t.done));

    // Sincronizar draggedTasks com pendingTasks
    watch(pendingTasks, (newVal) => {
        draggedTasks.value = newVal;
    }, { deep: true });

    async function loadTasks() {
        const response = await axios.get(`${API_URL}/tasks`);
        tasks.value = response.data;
    }

    async function createTask() {
        if (newTask.value.trim() === "") {
            return;
        }
        try {
            const formData = new FormData();
            formData.append("title", newTask.value);

            if (uploadImages.value.length > 0) {
                uploadImages.value.forEach(img => formData.append("images[]", img));
            }

            await axios.post(`${API_URL}/tasks`, formData, {
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
            await axios.delete(`${API_URL}/tasks/${id}`);
            tasks.value = tasks.value.filter(t => t.id !== id);
        } catch (error) {
            console.log(error.response?.data);
            loadTasks();
        }
    }
    
    async function updateTask(id) {
        try {
            await axios.put(`${API_URL}/tasks/${id}`, { done: true });
            const task = tasks.value.find(t => t.id === id);
            if (task) task.done = true;
        } catch (error) {
            console.log(error.response?.data);
            loadTasks();
        }
    }

    function handleImages(event) {
        uploadImages.value = Array.from(event.target.files);
        imagePreviews.value = uploadImages.value.map(file => URL.createObjectURL(file));
    }

    async function deleteSelectedTasks() {
        try {
            await Promise.all(
                selectedTasks.value.map(id =>
                axios.delete(`${API_URL}/tasks/${id}`)
                )
            )
            tasks.value = tasks.value.filter(t => !selectedTasks.value.includes(t.id));
            selectedTasks.value = [];
        } catch (error) {
            console.log(error.response?.data);
            loadTasks();
        }
    }

    async function deleteSelectedImages() {
        try {
            await Promise.all(
                selectedImages.value.map(imageId =>
                axios.delete(`${API_URL}/images/${imageId}`)
                )
            )
            tasks.value.forEach(t => {
                t.images = t.images.filter(img => !selectedImages.value.includes(img.id));
            });
            selectedImages.value = [];
        } catch (error) {
            console.log(error.response?.data);
            loadTasks();
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

            editImages.value.forEach(image => formData.append("images[]", image));

            await axios.post(
                `${API_URL}/tasks/${taskId}?_method=PUT`,
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

    async function saveTaskOrder() {
        if (isReorderingTasks.value) return
        isReorderingTasks.value = true

        const positions = draggedTasks.value.map((task, index) => ({
            id: task.id,
            position: index + 1
        }))

        try {
            await axios.post(`${API_URL}/tasks/reorder`, { positions })
        } catch (error) {
            console.error(error.response?.data || error)
            loadTasks()
        } finally {
            isReorderingTasks.value = false
        }
    }

    async function saveImageOrder(task) {
        if (isReorderingImages.value) return
        isReorderingImages.value = true
        
        const positions = task.images.map((img, index) => ({
            id: img.id,
            task_id: task.id,
            position: index + 1
        }))

        try {
            await axios.post(`${API_URL}/images/reorder`, {
                positions
            });
            await loadTasks();
        } catch (error) {
            console.error(error.response?.data || error)
        } finally {
            isReorderingImages.value = false
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

        <div v-if="pendingTasks.length === 0">
            Nenhuma task encontrada
        </div>

        <draggable
            v-model="draggedTasks"
            item-key="id"
            :disabled="isReorderingTasks"
            @change="saveTaskOrder"
        >
            <template #item="{ element: task }">
                <li style="margin-left: -25px; margin-bottom: 10px;">

                    <!-- seleção -->
                    <input type="checkbox" :value="task.id" v-model="selectedTasks"/>

                    <!-- EDIT MODE -->
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

                    <!-- VIEW MODE -->
                    <template v-else>
                        {{ task.title }}

                        <button @click="startEdit(task)">
                            Editar
                        </button>
                    </template>

                    <!-- IMAGENS -->
                    <draggable
                        v-model="task.images"
                        item-key="id"
                        :group="{ name: 'images', pull: true, put: true }"
                        :disabled="isReorderingImages"
                        @change="saveImageOrder(task)"
                        style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;"
                    >
                        <template #item="{ element: image }">
                            <div style="display:flex; flex-direction:column; align-items:center;">
                                
                                <input
                                    type="checkbox"
                                    :value="image.id"
                                    v-model="selectedImages"
                                    style="margin-bottom:5px;"
                                />

                                <img
                                    :src="`${STORAGE_URL}/${image.image}`"
                                    style="width:120px; height:120px; object-fit:cover; border-radius:10px;"
                                />
                            </div>
                        </template>
                    </draggable>

                    <!-- ações -->
                    <button @click="deleteTask(task.id)" style="margin-left: 10px;">
                        Deletar
                    </button>

                    <button @click="updateTask(task.id)" style="margin-left: 10px;">
                        Concluída
                    </button>

                </li>
            </template>
        </draggable>

        <!-- aqui vai ficar o separador  -->

        <div v-if="completedTasks.length === 0">
            Nenhuma task concluída
        </div>

        <ul v-else>
            <li v-for="task in completedTasks" :key="task.id" style="margin-left: -25px;">
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
