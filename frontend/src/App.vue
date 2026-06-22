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
    const editExistingImages = ref([]);
    const editImagePreviews = ref([]);
    const isReorderingTasks = ref(false);
    const isReorderingTasksImages = ref({});
    const pendingTasks = computed(() => tasks.value.filter(t => !t.done));
    const completedTasks = computed(() => tasks.value.filter(t => t.done));
    const isSelectionMode = ref(false);
    const isExitingSelectionMode = ref(false);
    const requestCount = ref(0);
    const isLoading = computed(() => requestCount.value > 0);
    const lightboxImage = ref(null);
    const justClosedLightbox = ref(false);
    let imageClickTimer = null;

    function handleImageClick(imageId, imageUrl) {
        if (isSelectionMode.value) {
            toggleImageSelection(imageId);
            return;
        }
        if (imageClickTimer) {
            clearTimeout(imageClickTimer);
            imageClickTimer = null;
            return;
        }
        imageClickTimer = setTimeout(() => {
            imageClickTimer = null;
            openLightbox(imageUrl);
        }, 250);
    }

    function handleImageDblClick(imageId) {
        if (imageClickTimer) {
            clearTimeout(imageClickTimer);
            imageClickTimer = null;
        }
        if (justClosedLightbox.value) {
            justClosedLightbox.value = false; // reseta imediatamente
            const task = tasks.value.find(t => t.images.some(img => img.id === imageId));
            const image = task?.images.find(img => img.id === imageId);
            if (image) {
                openLightbox(`${STORAGE_URL}/${image.image}`);
            }
            return;
        }
        enterSelectionModeFromImage(imageId);
    }

    axios.interceptors.request.use(config => {
        requestCount.value++;
        return config;
    }, error => {
        requestCount.value--;
        return Promise.reject(error);
    });

    axios.interceptors.response.use(response => {
        requestCount.value--;
        return response;
    }, error => {
        requestCount.value--;
        return Promise.reject(error);
    });

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
    
    async function toggleTaskDone(id, done) {
        try {
            const task = tasks.value.find(t => t.id === id);
            if (!task) return;
            await axios.put(`${API_URL}/tasks/${id}`, {
                title: task.title,
                done: done
            });
            task.done = done;
        } catch (error) {
            console.log(error.response?.data);
            loadTasks();
        }
    }

    function handleImages(event) {
        uploadImages.value = Array.from(event.target.files);
        imagePreviews.value = uploadImages.value.map(file => URL.createObjectURL(file));
    }

    function removeUploadImage(index) {
        uploadImages.value.splice(index, 1);
        imagePreviews.value.splice(index, 1);
    }

    async function deleteSelected() {
        try {
            const tasksDeletion = selectedTasks.value.length > 0
                ? Promise.all(selectedTasks.value.map(id => axios.delete(`${API_URL}/tasks/${id}`)))
                : Promise.resolve();
            const imagesDeletion = selectedImages.value.length > 0
                ? Promise.all(selectedImages.value.map(imageId => axios.delete(`${API_URL}/images/${imageId}`)))
                : Promise.resolve();

            await Promise.all([tasksDeletion, imagesDeletion]);

            tasks.value = tasks.value.filter(t => !selectedTasks.value.includes(t.id));
            tasks.value.forEach(t => {
                t.images = t.images.filter(img => !selectedImages.value.includes(img.id));
            });

            selectedTasks.value = [];
            selectedImages.value = [];
            isSelectionMode.value = false;
        } catch (error) {
            console.error(error.response?.data);
            loadTasks();
        }
    }

    function startEdit(task) {
        editingTask.value = task.id;
        editTitle.value = task.title;
        editExistingImages.value = [...task.images];
        editImages.value = [];
        editImagePreviews.value = [];
    }

    function handleEditImages(event) {
        editImages.value = Array.from(event.target.files);
        editImagePreviews.value = editImages.value.map(file => URL.createObjectURL(file));
    }

    function deleteExistingImage(imageId) {
        editExistingImages.value = editExistingImages.value.filter(img => img.id !== imageId);
    }

    function deleteNewImage(index) {
        editImages.value.splice(index, 1);
        editImagePreviews.value.splice(index, 1);
    }

    function cancelEdit() {
        editingTask.value = null;
        editTitle.value = "";
        editImages.value = [];
        editExistingImages.value = [];
        editImagePreviews.value = [];
    }

    async function saveEdit(taskId) {
        try {
            const task = tasks.value.find(t => t.id === taskId);
            
            const originalImageIds = task.images.map(img => img.id);
            const remainingImageIds = editExistingImages.value.map(img => img.id);
            const deletedImageIds = originalImageIds.filter(id => !remainingImageIds.includes(id));
            
            if (deletedImageIds.length > 0) {
                await Promise.all(
                    deletedImageIds.map(imageId =>
                        axios.delete(`${API_URL}/images/${imageId}`)
                    )
                );
            }
            
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
            editExistingImages.value = [];
            editImagePreviews.value = [];

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
        if (isReorderingTasksImages.value[task.id]) return;

        isReorderingTasksImages.value[task.id] = true;

        const positions = task.images.map((img, index) => ({
            id: img.id,
            task_id: task.id,
            position: index + 1
        }));

        try {
            await axios.post(`${API_URL}/images/reorder`, { positions });
            await loadTasks();
        } catch (error) {
            console.error(error.response?.data || error);
        } finally {
            delete isReorderingTasksImages.value[task.id];
        }
    }

    function isTaskReordering(taskId) {
        return !!isReorderingTasksImages.value[taskId];
    }

    function enterSelectionModeFromTask(taskId) {
        if (editingTask.value !== null) return;
        if (isExitingSelectionMode.value) return;
        if (isSelectionMode.value) return;
        selectedTasks.value = [taskId];
        selectedImages.value = [];
        isSelectionMode.value = true;
    }

    function enterSelectionModeFromImage(imageId) {
        if (editingTask.value !== null) return;
        if (isExitingSelectionMode.value) return;
        if (isSelectionMode.value) return;
        selectedTasks.value = [];
        selectedImages.value = [imageId];
        isSelectionMode.value = true;
    }

    function toggleTaskSelection(taskId) {
    if (editingTask.value !== null) return;
    if (!isSelectionMode.value || isExitingSelectionMode.value) return;

    const task = tasks.value.find(t => t.id === taskId);
    if (task && task.images.some(img => selectedImages.value.includes(img.id))) {
        return;
    }

    const index = selectedTasks.value.indexOf(taskId);
    if (index === -1) {
        selectedTasks.value.push(taskId);
    } else {
        selectedTasks.value.splice(index, 1);
    }
        if (selectedTasks.value.length === 0 && selectedImages.value.length === 0) {
            isExitingSelectionMode.value = true;
            isSelectionMode.value = false;
            setTimeout(() => {
                isExitingSelectionMode.value = false;
            }, 500);
        }
    }

    function toggleImageSelection(imageId) {
    if (editingTask.value !== null) return;
    if (!isSelectionMode.value || isExitingSelectionMode.value) return;

    let parentTask = null;
    for (const t of tasks.value) {
        if (t.images.some(img => img.id === imageId)) {
            parentTask = t;
            break;
        }
    }
    if (parentTask && selectedTasks.value.includes(parentTask.id)) {
        return;
    }

    const index = selectedImages.value.indexOf(imageId);
    if (index === -1) {
        selectedImages.value.push(imageId);
    } else {
        selectedImages.value.splice(index, 1);
    }
        if (selectedTasks.value.length === 0 && selectedImages.value.length === 0) {
            isExitingSelectionMode.value = true;
            isSelectionMode.value = false;
            setTimeout(() => {
                isExitingSelectionMode.value = false;
            }, 500);
        }
    }

    function openLightbox(imageUrl) {
        if (isSelectionMode.value) return; // no modo seleção, não abre
        lightboxImage.value = imageUrl;
    }

    function closeLightbox() {
        lightboxImage.value = null;
        justClosedLightbox.value = true;
        setTimeout(() => {
            justClosedLightbox.value = false;
        }, 500);
    }

    onMounted(() => {
        loadTasks();
    });
</script>

<style scoped src="./styles.css"></style>

<template>
    <div class="container">
        <h1>Lista de Tasks</h1>

        <div v-if="isLoading" class="loading-overlay">
            <div class="loading-box">
                <div class="spinner"></div>
                <span>Processando requisição...</span>
            </div>
        </div>

        <div class="form-container">
            <input v-model="newTask" placeholder="Digite uma task" class="input-task" />
            <input ref="fileInput" type="file" multiple @change="handleImages" :disabled="isLoading" />
            <button @click="createTask" :disabled="isLoading">Criar</button>
        </div>

        <div v-for="(preview, index) in imagePreviews" :key="index" class="preview-container">
            <img :src="preview" class="preview-image" @click="openLightbox(preview)" />
            <button @click="removeUploadImage(index)" class="remove-btn" :disabled="isLoading">X</button>
        </div>

        <div v-if="pendingTasks.length === 0">
            Nenhuma task encontrada
        </div>

        <draggable
            v-model="draggedTasks"
            tag="ul"
            item-key="id"
            :disabled="isReorderingTasks || isSelectionMode || isLoading"
            @change="saveTaskOrder"
        >
            <template #item="{ element: task }">
                <li
                    class="task-item"
                    @dblclick.stop="enterSelectionModeFromTask(task.id)"
                    @click="toggleTaskSelection(task.id)"
                    :class="{ 'selected-item': isSelectionMode && selectedTasks.includes(task.id) }"
                >
                    <!-- EDIT MODE -->
                    <template v-if="editingTask === task.id">
                        <input v-model="editTitle" placeholder="Título da task" :disabled="isLoading" />

                        <div class="section-spacing">
                            <h4>Imagens Existentes:</h4>
                            <div v-if="editExistingImages.length === 0" class="empty-text">Nenhuma imagem</div>
                            <div class="images-grid">
                                <div v-for="img in editExistingImages" :key="img.id" class="preview-container">
                                    <img
                                        :src="`${STORAGE_URL}/${img.image}`"
                                        class="preview-image-small"
                                        @click="openLightbox(`${STORAGE_URL}/${img.image}`)"
                                    />
                                    <button @click="deleteExistingImage(img.id)" class="remove-btn" :disabled="isLoading">X</button>
                                </div>
                            </div>
                        </div>

                        <div class="bottom-spacing">
                            <h4>Adicionar Novas Imagens:</h4>
                            <input type="file" multiple @change="handleEditImages" :disabled="isLoading" />
                        </div>

                        <div v-if="editImagePreviews.length > 0" class="bottom-spacing">
                            <h4>Preview das Novas Imagens:</h4>
                            <div class="images-grid">
                                <div v-for="(preview, index) in editImagePreviews" :key="index" class="preview-container">
                                    <img
                                        :src="preview"
                                        class="preview-image-small preview-image-new"
                                        @click="openLightbox(preview)"
                                    />
                                    <button @click="deleteNewImage(index)" class="remove-btn" :disabled="isLoading">X</button>
                                </div>
                            </div>
                        </div>

                        <button @click="saveEdit(task.id)" class="btn-spacing" :disabled="isLoading">Salvar</button>
                        <button @click="cancelEdit" :disabled="isLoading">Cancelar</button>
                    </template>

                    <!-- VIEW MODE -->
                    <template v-else>
                        {{ task.title }}
                        <button @click.stop="startEdit(task)" :disabled="isLoading">Editar</button>
                    </template>

                    <!-- IMAGENS -->
                    <draggable
                        v-if="editingTask !== task.id"
                        v-model="task.images"
                        item-key="id"
                        :group="{ name: 'images', pull: true, put: ['images'] }"
                        :disabled="isTaskReordering(task.id) || isSelectionMode || isLoading"
                        @change="saveImageOrder(task)"
                        class="images-grid images-grid-margin"
                    >
                        <template #item="{ element: image }">
                            <div
                                class="image-item"
                                @click.stop="handleImageClick(image.id, `${STORAGE_URL}/${image.image}`)"
                                @dblclick.stop="handleImageDblClick(image.id)"
                                :class="{ 'selected-image': isSelectionMode && selectedImages.includes(image.id) }"
                            >
                                <img
                                    :src="`${STORAGE_URL}/${image.image}`"
                                    class="preview-image"
                                />
                            </div>
                        </template>
                    </draggable>

                    <button @click.stop="deleteTask(task.id)" class="btn-left-spacing" :disabled="isLoading">Deletar</button>
                    <button @click.stop="toggleTaskDone(task.id, true)" class="btn-left-spacing" :disabled="isLoading">Concluída</button>
                </li>
            </template>
        </draggable>

        <div v-if="completedTasks.length === 0">
            Nenhuma task concluída
        </div>

        <ul>
            <li
                v-for="task in completedTasks"
                :key="task.id"
                class="completed-task"
                @dblclick.stop="enterSelectionModeFromTask(task.id)"
                @click="toggleTaskSelection(task.id)"
                :class="{ 'selected-item': isSelectionMode && selectedTasks.includes(task.id) }"
            >
                {{ task.title }}
                <button @click.stop="deleteTask(task.id)" class="btn-left-spacing" :disabled="isLoading">Deletar</button>
                <button @click.stop="toggleTaskDone(task.id, false)" class="btn-left-spacing" :disabled="isLoading">Reabrir</button>
            </li>
        </ul>

        <button v-if="isSelectionMode" @click.stop="deleteSelected" :disabled="isLoading">
            Deletar Selecionados ({{ selectedTasks.length + selectedImages.length }})
        </button>
    </div>

    <div v-if="lightboxImage" class="lightbox-overlay" @click="closeLightbox">
        <div class="lightbox-content" @click.stop>
            <img :src="lightboxImage" class="lightbox-image" />
            <button class="lightbox-close" @click="closeLightbox">✕</button>
        </div>
    </div>
</template>