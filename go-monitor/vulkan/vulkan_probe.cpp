#include <iostream>
#include <vector>
#include <vulkan/vulkan.h>

int main() {
    VkApplicationInfo appInfo{};
    appInfo.sType = VK_STRUCTURE_TYPE_APPLICATION_INFO;
    appInfo.pApplicationName = "S1 Vulkan Monitor Probe";
    appInfo.applicationVersion = VK_MAKE_VERSION(1, 0, 0);
    appInfo.pEngineName = "UniActivityEngine";
    appInfo.engineVersion = VK_MAKE_VERSION(1, 0, 0);
    appInfo.apiVersion = VK_API_VERSION_1_0;

    VkInstanceCreateInfo createInfo{};
    createInfo.sType = VK_STRUCTURE_TYPE_INSTANCE_CREATE_INFO;
    createInfo.pApplicationInfo = &appInfo;

    VkInstance instance = VK_NULL_HANDLE;
    VkResult res = vkCreateInstance(&createInfo, nullptr, &instance);
    if (res != VK_SUCCESS) {
        std::cout << "{\"available\": false, \"error\": \"vkCreateInstance failed (" << res << ")\"}" << std::endl;
        return 0;
    }

    uint32_t deviceCount = 0;
    vkEnumeratePhysicalDevices(instance, &deviceCount, nullptr);

    if (deviceCount == 0) {
        std::cout << "{\"available\": false, \"error\": \"No Vulkan physical devices found\"}" << std::endl;
        vkDestroyInstance(instance, nullptr);
        return 0;
    }

    std::vector<VkPhysicalDevice> devices(deviceCount);
    vkEnumeratePhysicalDevices(instance, &deviceCount, devices.data());

    VkPhysicalDeviceProperties props;
    vkGetPhysicalDeviceProperties(devices[0], &props);

    VkPhysicalDeviceMemoryProperties memProps;
    vkGetPhysicalDeviceMemoryProperties(devices[0], &memProps);

    VkPhysicalDeviceFeatures features;
    vkGetPhysicalDeviceFeatures(devices[0], &features);

    uint64_t totalDeviceLocalMemory = 0;
    for (uint32_t h = 0; h < memProps.memoryHeapCount; ++h) {
        if (memProps.memoryHeaps[h].flags & VK_MEMORY_HEAP_DEVICE_LOCAL_BIT) {
            totalDeviceLocalMemory += memProps.memoryHeaps[h].size;
        }
    }

    const char* typeStr = "OTHER";
    if (props.deviceType == VK_PHYSICAL_DEVICE_TYPE_INTEGRATED_GPU) typeStr = "INTEGRATED";
    else if (props.deviceType == VK_PHYSICAL_DEVICE_TYPE_DISCRETE_GPU) typeStr = "DISCRETE";
    else if (props.deviceType == VK_PHYSICAL_DEVICE_TYPE_VIRTUAL_GPU) typeStr = "VIRTUAL";
    else if (props.deviceType == VK_PHYSICAL_DEVICE_TYPE_CPU) typeStr = "CPU";

    std::cout << "{\n";
    std::cout << "  \"available\": true,\n";
    std::cout << "  \"gpu_count\": " << deviceCount << ",\n";
    std::cout << "  \"name\": \"" << props.deviceName << "\",\n";
    std::cout << "  \"vendor_id\": \"0x" << std::hex << props.vendorID << std::dec << "\",\n";
    std::cout << "  \"device_id\": \"0x" << std::hex << props.deviceID << std::dec << "\",\n";
    std::cout << "  \"type\": \"" << typeStr << "\",\n";
    std::cout << "  \"api_version\": \"" 
              << VK_VERSION_MAJOR(props.apiVersion) << "."
              << VK_VERSION_MINOR(props.apiVersion) << "."
              << VK_VERSION_PATCH(props.apiVersion) << "\",\n";
    std::cout << "  \"driver_version\": " << props.driverVersion << ",\n";
    std::cout << "  \"vram_bytes\": " << totalDeviceLocalMemory << ",\n";
    std::cout << "  \"vram_mb\": " << (totalDeviceLocalMemory / (1024 * 1024)) << ",\n";
    std::cout << "  \"max_compute_shared_memory\": " << props.limits.maxComputeSharedMemorySize << ",\n";
    std::cout << "  \"max_compute_work_group_invocations\": " << props.limits.maxComputeWorkGroupInvocations << ",\n";
    std::cout << "  \"geometry_shader\": " << (features.geometryShader ? "true" : "false") << ",\n";
    std::cout << "  \"tessellation_shader\": " << (features.tessellationShader ? "true" : "false") << "\n";
    std::cout << "}\n";

    vkDestroyInstance(instance, nullptr);
    return 0;
}
