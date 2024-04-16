FROM centos:7

RUN set -eux; \
    echo 'ip_resolve = IPv4' >>/etc/yum.conf; \
    yum update -y; \
    yum install -y \
        centos-release-scl \
        curl \
        environment-modules \
        gcc \
        gcc-c++ \
        git \
        libcurl-devel \
        libedit-devel \
        make \
        openssl-devel \
# data dumper needed for autoconf, apparently
        perl-Data-Dumper \
        pkg-config \
        scl-utils \
        unzip \
        vim \
        xz; \
    yum update nss nss-util nss-sysinit nss-tools; \
    yum install -y devtoolset-7; \
    yum clean all;

ENV SRC_DIR=/usr/local/src

COPY download-src.sh /root/

# Latest version of m4 required
RUN source scl_source enable devtoolset-7; set -eux; \
    /root/download-src.sh m4 https://ftp.gnu.org/gnu/m4/m4-1.4.18.tar.gz; \
    cd "${SRC_DIR}/m4"; \
    mkdir -v 'build' && cd 'build'; \
    ../configure && make -j $(nproc) && make install; \
    cd - && rm -fr build

# Latest version of autoconf required
RUN set -eux; \
    /root/download-src.sh autoconf https://ftp.gnu.org/gnu/autoconf/autoconf-2.69.tar.gz; \
    cd "${SRC_DIR}/autoconf"; \
    mkdir -v 'build' && cd 'build'; \
    ../configure && make -j $(nproc) && make install; \
    cd - && rm -fr build

# Required: libxml >= 2.9.0 (default version is 2.7.6)
RUN source scl_source enable devtoolset-7; set -eux; \
    /root/download-src.sh libxml2 http://xmlsoft.org/sources/libxml2-2.9.10.tar.gz; \
    cd "${SRC_DIR}/libxml2"; \
    mkdir -v 'build' && cd 'build'; \
    ../configure --with-python=no && make -j $(nproc) && make install; \
    cd - && rm -fr build

# Required: libffi >= 3.0.11 (default version is 3.0.5)
RUN source scl_source enable devtoolset-7; set -eux; \
    /root/download-src.sh libffi https://github.com/libffi/libffi/releases/download/v3.4.2/libffi-3.4.2.tar.gz; \
    cd "${SRC_DIR}/libffi"; \
    mkdir -v 'build' && cd 'build'; \
    ../configure && make -j $(nproc) && make install; \
    cd - && rm -fr build

# Required: oniguruma (not installed by deafult)
RUN source scl_source enable devtoolset-7; set -eux; \
    /root/download-src.sh oniguruma https://github.com/kkos/oniguruma/releases/download/v6.9.5_rev1/onig-6.9.5-rev1.tar.gz; \
    cd "${SRC_DIR}/oniguruma"; \
    mkdir -v 'build' && cd 'build'; \
    ../configure && make -j $(nproc) && make install; \
    cd - && rm -fr build

# Required: bison >= 3.0.0 (not installed by deafult)
RUN source scl_source enable devtoolset-7; set -eux; \
    /root/download-src.sh bison https://ftp.gnu.org/gnu/bison/bison-3.7.3.tar.gz; \
    cd "${SRC_DIR}/bison"; \
    mkdir -v 'build' && cd 'build'; \
    ../configure && make -j $(nproc) && make install; \
    cd - && rm -fr build

# Required: re2c >= 0.13.4 (not installed by deafult)
RUN source scl_source enable devtoolset-7; set -eux; \
    /root/download-src.sh re2c https://github.com/skvadrik/re2c/releases/download/2.0.3/re2c-2.0.3.tar.xz; \
    cd "${SRC_DIR}/re2c"; \
    mkdir -v 'build' && cd 'build'; \
    ../configure && make -j $(nproc) && make install; \
    cd - && rm -fr build

# Required: CMake >= 3.0.2 (default version is 2.8.12.2).
# LLVM warns about wanting 3.20 or newer.
# Required to build libzip from source (has to be a separate RUN layer)
RUN source scl_source enable devtoolset-7; set -eux; \
    version="3.24.4"; \
    arch=$(uname -m); \
    filename="cmake-${version}-linux-$arch.tar.gz"; \
    sha256_x86_64="cac77d28fb8668c179ac02c283b058aeb846fe2133a57d40b503711281ed9f19"; \
    sha256_aarch64="86f823f2636bf715af89da10e04daa476755a799d451baee66247846e95d7bee"; \
    sha256=$(if [[ $arch == "x86_64" ]]; then echo ${sha256_x86_64}; \
     elif [[ $arch == "aarch64" ]]; then echo ${sha256_aarch64}; fi); \
    curl -OL https://github.com/Kitware/CMake/releases/download/v${version}/${filename}; \
    echo "$sha256  cmake-${version}-linux-$arch.tar.gz" | sha256sum -c; \
    tar -xf "../${filename}" -C /usr/local --strip-components=1; \
    command -v cmake; \
    rm -fv "${filename}"

# Required: libzip >= 0.11 (default version is 0.9)
RUN source scl_source enable devtoolset-7; set -eux; \
    /root/download-src.sh libzip https://libzip.org/download/libzip-1.7.3.tar.gz; \
    cd "${SRC_DIR}/libzip"; \
    mkdir build && cd build; \
    cmake .. && make -j $(nproc) && make install;

ENV PKG_CONFIG_PATH="${PKG_CONFIG_PATH}:/usr/local/lib/pkgconfig:/usr/local/lib64/pkgconfig"

# LLVM, and Ninja to build LLVM
# Caution, takes a very long time! Since we have to build one from source,
# I picked LLVM 16, which matches Rust 1.71.
# Ordinarily we leave sources, but LLVM is 2GiB just for the sources...
# Minimum: libclang. Nice-to-have: full toolchain including linker to play
# with cross-language link-time optimization. Needs to match rustc -Vv's llvm
# version.
RUN source scl_source enable devtoolset-7 \
  && yum install -y python3 \
  && /root/download-src.sh ninja https://github.com/ninja-build/ninja/archive/refs/tags/v1.12.0.tar.gz \
  && mkdir vp "${SRC_DIR}/ninja/build" \
  && cd "${SRC_DIR}/ninja/build" \
  && ../configure.py --bootstrap --verbose \
  && strip ninja \
  && mv -v ninja /usr/local/bin/ \
  && cd - \
  && rm -fr "${SRC_DIR}/ninja" \
  && cd /usr/local/src \
  && git clone --depth 1 -b release/16.x https://github.com/llvm/llvm-project.git \
  && mkdir -vp llvm-project/build \
  && cd llvm-project/build \
  && cmake -G Ninja -DLLVM_ENABLE_PROJECTS="clang;lld" -DLLVM_TARGETS_TO_BUILD=host -DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX=/usr/local ../llvm \
  && cmake --build . --parallel $(nproc) --target "install/strip" \
  && rm -f /usr/local/lib/libclang*.a /usr/local/lib/libLLVM*.a \
  && cd - \
  && rm -fr llvm-project \
  && yum remove -y python3 \
  && yum clean all

# rustup-init sha256sum can be found by appending .sha256 to the download url
ARG RUST_VERSION="1.71.1"
ARG RUST_SHA256_ARM="76cd420cb8a82e540025c5f97bda3c65ceb0b0661d5843e6ef177479813b0367"
ARG RUST_SHA256_X86="a3d541a5484c8fa2f1c21478a6f6c505a778d473c21d60a18a4df5185d320ef8"
# Mount a cache into /rust/cargo if you want to pre-fetch packages or something
ENV CARGO_HOME=/rust/cargo
ENV RUSTUP_HOME=/rust/rustup
RUN source scl_source enable devtoolset-7 \
    && mkdir -p -v "${CARGO_HOME}" "${RUSTUP_HOME}" \
    && chown -R 777 "${CARGO_HOME}" "${RUSTUP_HOME}" \
    && RUSTUP_VERSION="1.27.0" \
    && MARCH=$(uname -m) \
    && triplet="$MARCH-unknown-linux-gnu" \
    && RUST_SHA256=$(if [[ $MARCH == "x86_64" ]]; then echo ${RUST_SHA256_X86}; \
     elif [[ $MARCH == "aarch64" ]]; then echo ${RUST_SHA256_ARM}; fi) \
    && curl -L --write-out '%{http_code}' -O https://static.rust-lang.org/rustup/archive/${RUSTUP_VERSION}/${triplet}/rustup-init \
    && printf '%s  rustup-init' "$RUST_SHA256" | sha256sum --check --status \
    && chmod +x "rustup-init" \
    && ./rustup-init -y --default-toolchain "$RUST_VERSION" -c "rustc,cargo,clippy,rustfmt,rust-std,rust-src" \
    && rm -fr "rustup-init"

# now install PHP specific dependencies
RUN set -eux; \
    rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm; \
    yum update -y; \
    yum install -y \
    re2c \
    bzip2-devel \
    httpd-devel \
    libmemcached-devel \
    libsodium-devel \
    libsqlite3x-devel \
    libxml2-devel \
    libxslt-devel \
    postgresql-devel \
    readline-devel \
    zlib-devel; \
    yum clean all;

RUN printf "source scl_source enable devtoolset-7" | tee -a /etc/profile.d/zzz-ddtrace.sh /etc/bashrc
ENV BASH_ENV="/etc/profile.d/zzz-ddtrace.sh"

ENV PATH="/rust/cargo/bin:${PATH}"

RUN echo '#define SECBIT_NO_SETUID_FIXUP (1 << 2)' > '/usr/include/linux/securebits.h'
