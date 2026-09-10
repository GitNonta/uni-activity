cmake_minimum_required(VERSION 3.10)
project(face_extract LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

find_package(ncnn REQUIRED)
find_package(OpenCV REQUIRED)

add_executable(face_extract face_extract.cpp)
target_link_libraries(face_extract PRIVATE ncnn ${OpenCV_LIBS})