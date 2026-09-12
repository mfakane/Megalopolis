FROM debian:bookworm-slim AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential ca-certificates curl bzip2 \
        libmariadb-dev-compat libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /usr/src
RUN curl -fL --retry 3 https://museum.php.net/php5/php-5.2.5.tar.bz2 -o php.tar.bz2 \
    && echo '5cac1e70df5019ebdfdab2e0b8b216f7fdf56b9895c9f68c993313918249bba3  php.tar.bz2' | sha256sum -c - \
    && tar -xjf php.tar.bz2

WORKDIR /usr/src/php-5.2.5
ARG HTTP_TESTS=0
# GCC now defaults to -fno-common and C99 inline semantics; PHP 5.2 predates both.
RUN if [ "$HTTP_TESTS" = 1 ]; then compat_sapi='--enable-cgi --enable-json --enable-session'; else compat_sapi='--disable-cgi'; fi \
    && CFLAGS='-O2 -fcommon -fgnu89-inline' ./configure \
        --prefix=/opt/php52 \
        --with-config-file-path=/opt/php52/etc \
        --disable-all $compat_sapi --enable-cli --without-pear \
        --enable-pdo --with-pdo-sqlite=/usr --with-pdo-mysql=/usr/bin/mysql_config \
        --enable-mbstring --enable-hash --with-pcre-regex \
    && make -j2 \
    && make install-cli \
    && strip /opt/php52/bin/php \
    && if [ "$HTTP_TESTS" = 1 ]; then cp sapi/cgi/php-cgi /opt/php52/bin/; strip /opt/php52/bin/php-cgi; fi

WORKDIR /usr/src
# r46, independent of the branch used to build this compatibility environment.
RUN curl -fL --retry 3 \
        https://codeload.github.com/mfakane/Megalopolis/tar.gz/dff38a8ab25b758e8bb7ea1a621cf33983e9e54e \
        -o r46.tar.gz \
    && echo 'd3b729d654d3bedbabb306222c8ed4a02f0b082c7ecd338df18a4ec70510c38c  r46.tar.gz' | sha256sum -c - \
    && mkdir /opt/megalopolis-r46 \
    && tar -xzf r46.tar.gz --strip-components=1 -C /opt/megalopolis-r46

FROM debian:bookworm-slim
RUN apt-get update \
    && apt-get install -y --no-install-recommends libmariadb3 libsqlite3-0 \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir /fixtures \
    && chown 1000:1000 /fixtures

COPY --from=build /opt/php52/bin/ /opt/php52/bin/
COPY --from=build /opt/megalopolis-r46 /opt/megalopolis-r46
COPY php.ini /opt/php52/etc/php.ini
# Use the application's configuration hook to run the CLI fixture checks after
# the original r46 bootstrap has loaded its classes, before the web dispatcher.
COPY fixture.php /opt/megalopolis-r46/config.php
COPY generate.php corpus.php /opt/php52/fixtures/

ENV PATH="/opt/php52/bin:${PATH}"
WORKDIR /opt/megalopolis-r46
USER 1000:1000
ENTRYPOINT ["php"]
CMD ["index.php"]
