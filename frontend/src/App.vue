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

    function removeUploadImage(index) {
        uploadImages.value.splice(index, 1);
        imagePreviews.value.splice(index, 1);
    }

    async function deleteSelected() {
        try {
            // Executa as deleções de tasks e imagens em paralelo
            const tasksDeletion = selectedTasks.value.length > 0
                ? Promise.all(selectedTasks.value.map(id => axios.delete(`${API_URL}/tasks/${id}`)))
                : Promise.resolve();
            const imagesDeletion = selectedImages.value.length > 0
                ? Promise.all(selectedImages.value.map(imageId => axios.delete(`${API_URL}/images/${imageId}`)))
                : Promise.resolve();

            await Promise.all([tasksDeletion, imagesDeletion]);

            // Atualiza o estado local removendo os itens deletados
            tasks.value = tasks.value.filter(t => !selectedTasks.value.includes(t.id));
            tasks.value.forEach(t => {
                t.images = t.images.filter(img => !selectedImages.value.includes(img.id));
            });

            // Limpa as seleções e desativa o modo
            selectedTasks.value = [];
            selectedImages.value = [];
            isSelectionMode.value = false;
        } catch (error) {
            console.error(error.response?.data);
            loadTasks(); // recarrega em caso de erro
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
        // Se essa task já está salvando, ignora a chamada
        if (isReorderingTasksImages.value[task.id]) return;

        // Marca que essa task está salvando
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
        return; // não permite selecionar a task se já tem imagem dela
    }

    const index = selectedTasks.value.indexOf(taskId);
    if (index === -1) {
        selectedTasks.value.push(taskId);
    } else {
        selectedTasks.value.splice(index, 1);
    }
        if (selectedTasks.value.length === 0 && selectedImages.value.length === 0) {
            isExitingSelectionMode.value = true;   // ativa a trava
            isSelectionMode.value = false;
            setTimeout(() => {
                isExitingSelectionMode.value = false;  // libera após 200ms
            }, 500);
        }
    }

    function toggleImageSelection(imageId) {
    if (editingTask.value !== null) return;
    if (!isSelectionMode.value || isExitingSelectionMode.value) return;

    // Descobre a qual task a imagem pertence (procurando em todas as tasks)
    let parentTask = null;
    for (const t of tasks.value) {
        if (t.images.some(img => img.id === imageId)) {
            parentTask = t;
            break;
        }
    }
    // Se a task pai já está selecionada, impede a seleção da imagem
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
            isExitingSelectionMode.value = true;   // ativa a trava
            isSelectionMode.value = false;
            setTimeout(() => {
                isExitingSelectionMode.value = false;  // libera após 200ms
            }, 500);
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
            <div v-for="(preview, index) in imagePreviews" :key="index" style="position: relative;">
                <img 
                    :src="preview" 
                    style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px;" 
                />
                <button 
                    @click="removeUploadImage(index)" 
                    style="position: absolute; top: 2px; right: 2px; padding: 2px 6px; background: red; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;"
                >
                    X
                </button>
            </div>
        </div>

        <div v-if="pendingTasks.length === 0">
            Nenhuma task encontrada
        </div>

        <draggable
            v-model="draggedTasks"
            item-key="id"
            :disabled="isReorderingTasks || isSelectionMode"
            @change="saveTaskOrder"
        >
            <template #item="{ element: task }">
            <li
                style="margin-left: -25px; margin-bottom: 10px;"
                @dblclick.stop="enterSelectionModeFromTask(task.id)"
                @click="toggleTaskSelection(task.id)"
                :class="{ 'selected-item': isSelectionMode && selectedTasks.includes(task.id) }"
            >

                    <!-- EDIT MODE -->
                    <template v-if="editingTask === task.id">
                        <input v-model="editTitle" placeholder="Título da task" />

                        <div style="margin-top: 10px; margin-bottom: 10px;">
                            <h4>Imagens Existentes:</h4>
                            <div v-if="editExistingImages.length === 0" style="font-size: 12px; color: #999;">
                                Nenhuma imagem
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <div v-for="img in editExistingImages" :key="img.id" style="position: relative;">
                                    <img 
                                        :src="`${STORAGE_URL}/${img.image}`"
                                        style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px;"
                                    />
                                    <button 
                                        @click="deleteExistingImage(img.id)"
                                        style="position: absolute; top: 2px; right: 2px; padding: 2px 6px; background: red; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;"
                                    >
                                        X
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <h4>Adicionar Novas Imagens:</h4>
                            <input
                                type="file"
                                multiple
                                @change="handleEditImages"
                            />
                        </div>

                        <div v-if="editImagePreviews.length > 0" style="margin-bottom: 10px;">
                            <h4>Preview das Novas Imagens:</h4>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <div v-for="(preview, index) in editImagePreviews" :key="index" style="position: relative;">
                                    <img 
                                        :src="preview"
                                        style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px; border: 2px solid #4CAF50;"
                                    />
                                    <button 
                                        @click="deleteNewImage(index)"
                                        style="position: absolute; top: 2px; right: 2px; padding: 2px 6px; background: red; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;"
                                    >
                                        X
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button @click="saveEdit(task.id)" style="margin-right: 10px;">
                            Salvar
                        </button>

                        <button @click="cancelEdit">
                            Cancelar
                        </button>
                    </template>

                    <!-- VIEW MODE -->
                    <template v-else>
                        {{ task.title }}

                        <button @click.stop="startEdit(task)">Editar</button>

                    </template>

                    <!-- IMAGENS -->
                    <draggable
                        v-if="editingTask !== task.id"
                        v-model="task.images"
                        item-key="id"
                        :group="{ name: 'images', pull: true, put: ['images'] }"
                        :disabled="isTaskReordering(task.id) || isSelectionMode"
                        @change="saveImageOrder(task)"
                        style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;"
                    >
                        <template #item="{ element: image }">
                        <div
                            style="display:flex; flex-direction:column; align-items:center;"
                            @dblclick.stop="enterSelectionModeFromImage(image.id)"
                            @click.stop="toggleImageSelection(image.id)"
                            :class="{ 'selected-image': isSelectionMode && selectedImages.includes(image.id) }"
                        >

                                <img
                                    :src="`${STORAGE_URL}/${image.image}`"
                                    style="width:120px; height:120px; object-fit:cover; border-radius:10px;"
                                />
                            </div>
                        </template>
                    </draggable>

                    <!-- ações -->
                    <button @click.stop="deleteTask(task.id)" style="margin-left: 10px;">Deletar</button>

                    <button @click.stop="updateTask(task.id)" style="margin-left: 10px;">Concluída</button>

                </li>
            </template>
        </draggable>

        <!-- aqui vai ficar o separador  -->

        <div v-if="completedTasks.length === 0">
            Nenhuma task concluída
        </div>

        <li
            v-for="task in completedTasks" 
            :key="task.id" 
            style="margin-left: -25px;"
            @dblclick.stop="enterSelectionModeFromTask(task.id)"
            @click="toggleTaskSelection(task.id)"
            :class="{ 'selected-item': isSelectionMode && selectedTasks.includes(task.id) }"
        >
            {{ task.title }}
            <button @click.stop="deleteTask(task.id)" style="margin-left: 10px;">
                Deletar
            </button>
        </li>
        <template v-if="isSelectionMode">
            <button v-if="isSelectionMode" @click.stop="deleteSelected">
                Deletar Selecionados ({{ selectedTasks.length + selectedImages.length }})
            </button>
        </template>
    </div>
</template>

<style scoped>
    .selected-item {
        outline: 2px solid red;
        outline-offset: 2px;
    }
    .selected-image img {
        outline: 2px solid red;
        outline-offset: 2px;
    }
</style>
